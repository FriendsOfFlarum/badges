<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Job;

use Flarum\Queue\AbstractJob;
use FoF\Badges\BadgeRecalculationProgress;
use FoF\Badges\Service\BadgeRecalculationOptimizer;
use FoF\Badges\Service\BadgeRecalculationService;
use Illuminate\Contracts\Queue\Queue;
use Throwable;

/**
 * Orchestrator job that dispatches chunk jobs for badge recalculation.
 * This job is short-lived - it only sets up the work and dispatches chunks.
 *
 * Uses smart grouping to optimize multi-badge recalculation:
 * - Badges with similar conditions are grouped together
 * - Each group gets its own optimized user query
 * - This dramatically reduces the number of user evaluations
 */
class RecalculateBadgesJob extends AbstractJob
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    protected int $progressId;

    public function __construct(int $progressId)
    {
        $this->progressId = $progressId;
    }

    public function handle(
        BadgeRecalculationService $service,
        BadgeRecalculationOptimizer $optimizer,
        Queue $queue
    ): void {
        $progress = BadgeRecalculationProgress::find($this->progressId);

        if (!$progress) {
            return;
        }

        if ($progress->isCancelled()) {
            return;
        }

        try {
            $this->dispatchChunks($progress, $service, $optimizer, $queue);
        } catch (Throwable $e) {
            $progress->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    protected function dispatchChunks(
        BadgeRecalculationProgress $progress,
        BadgeRecalculationService $service,
        BadgeRecalculationOptimizer $optimizer,
        Queue $queue
    ): void {
        $badges = $service->getBadgesToEvaluate($progress->badge_id);

        if ($badges->isEmpty()) {
            $progress->markAsCompleted();
            return;
        }

        $chunkSize = $progress->chunk_size ?: 100;
        $noRevoke = (bool) $progress->no_revoke;

        $reapplyActions = (bool) $progress->reapply_actions;

        // Single badge: use simple per-badge optimization
        if (count($badges) === 1) {
            $this->dispatchSingleBadgeChunks($progress, $badges->first(), $optimizer, $queue, $chunkSize, $noRevoke, $reapplyActions);
            return;
        }

        // Multiple badges: use smart grouping for optimization
        $this->dispatchGroupedBadgeChunks($progress, $badges, $optimizer, $queue, $chunkSize, $noRevoke, $reapplyActions);
    }

    /**
     * Dispatch chunks for a single badge with full optimization.
     */
    protected function dispatchSingleBadgeChunks(
        BadgeRecalculationProgress $progress,
        \FoF\Badges\Badge $badge,
        BadgeRecalculationOptimizer $optimizer,
        Queue $queue,
        int $chunkSize,
        bool $noRevoke,
        bool $reapplyActions = false
    ): void {
        // Check if this is a manual badge (no trigger_config)
        $isManualBadge = empty($badge->trigger_config);

        // For manual badges, only query users who already have the badge
        if ($isManualBadge) {
            $userIds = $optimizer->buildOptimizedUserQuery($badge, [], true)
                ->pluck('id')
                ->toArray();
        } else {
            $skipUserIds = $optimizer->getUserIdsToSkip($badge, $noRevoke, $reapplyActions);
            $userIds = $optimizer->buildOptimizedUserQuery($badge, $skipUserIds)
                ->pluck('id')
                ->toArray();
        }

        $totalUsers = count($userIds);

        if ($totalUsers === 0) {
            $progress->markAsRunning(0, 1, 0);
            $progress->markAsCompleted();
            return;
        }

        $chunks = array_chunk($userIds, $chunkSize);
        $totalChunks = count($chunks);

        $progress->markAsRunning($totalUsers, 1, $totalChunks);

        foreach ($chunks as $chunkIndex => $chunkUserIds) {
            $queue->push(new ProcessBadgeChunkJob(
                $this->progressId,
                $chunkUserIds,
                [$badge->id],
                $noRevoke,
                $chunkIndex,
                $reapplyActions
            ));
        }
    }

    /**
     * Dispatch chunks for multiple badges using smart grouping.
     *
     * Badges are grouped by their filterable conditions:
     * - All post_count badges together (query: comment_count >= min threshold)
     * - All has_avatar badges together (query: avatar_url IS NOT NULL)
     * - etc.
     *
     * This reduces total user evaluations significantly.
     */
    protected function dispatchGroupedBadgeChunks(
        BadgeRecalculationProgress $progress,
        \Illuminate\Support\Collection $badges,
        BadgeRecalculationOptimizer $optimizer,
        Queue $queue,
        int $chunkSize,
        bool $noRevoke,
        bool $reapplyActions = false
    ): void {
        // Group badges by their optimization strategy
        $groups = $optimizer->groupBadgesByOptimizationStrategy($badges, $noRevoke, $reapplyActions);

        if (empty($groups)) {
            $progress->markAsCompleted();
            return;
        }

        // Calculate totals across all groups
        $totalUsers = 0;
        $totalChunks = 0;
        $allChunkJobs = [];

        foreach ($groups as $group) {
            $groupUserIds = $group['userIds'];
            $groupBadgeIds = $group['badgeIds'];

            if (empty($groupUserIds)) {
                continue;
            }

            $chunks = array_chunk($groupUserIds, $chunkSize);

            foreach ($chunks as $chunkUserIds) {
                $allChunkJobs[] = [
                    'userIds' => $chunkUserIds,
                    'badgeIds' => $groupBadgeIds,
                ];
                $totalChunks++;
            }

            $totalUsers += count($groupUserIds);
        }

        if ($totalChunks === 0) {
            $progress->markAsRunning(0, count($badges), 0);
            $progress->markAsCompleted();
            return;
        }

        // Mark progress with totals
        $progress->markAsRunning($totalUsers, count($badges), $totalChunks);

        // Dispatch all chunk jobs
        foreach ($allChunkJobs as $chunkIndex => $job) {
            $queue->push(new ProcessBadgeChunkJob(
                $this->progressId,
                $job['userIds'],
                $job['badgeIds'],
                $noRevoke,
                $chunkIndex,
                $reapplyActions
            ));
        }
    }
}
