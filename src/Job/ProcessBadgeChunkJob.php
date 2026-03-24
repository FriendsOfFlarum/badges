<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Job;

use Flarum\Queue\AbstractJob;
use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\BadgeRecalculationProgress;
use FoF\Badges\Service\BadgeRecalculationService;
use Throwable;

/**
 * Processes a single chunk of users for badge recalculation.
 * This job is designed to be short-lived to avoid queue timeout issues.
 */
class ProcessBadgeChunkJob extends AbstractJob
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    public function __construct(protected int $progressId, protected array $userIds, protected array $badgeIds, protected bool $noRevoke, protected int $chunkIndex, protected bool $reapplyActions = false)
    {
    }

    public function handle(BadgeRecalculationService $service): void
    {
        $progress = BadgeRecalculationProgress::find($this->progressId);

        if (! $progress) {
            return;
        }

        // Check if job was cancelled
        if ($progress->isCancelled()) {
            return;
        }

        try {
            $this->processChunk($progress, $service);
        } catch (Throwable $e) {
            // Log the error
            resolve('log')->error('[Badges] Chunk processing error', [
                'progress_id' => $this->progressId,
                'chunk_index' => $this->chunkIndex,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still increment the chunk counter so we don't get stuck
            // but don't count the users as processed
            try {
                $progress->incrementChunkProgress(0, 0, 0, 0);
            } catch (Throwable $updateError) {
                // Ignore update errors
            }

            // Don't re-throw - we don't want the job to retry
            // as it would process the same users again
        }
    }

    protected function processChunk(BadgeRecalculationProgress $progress, BadgeRecalculationService $service): void
    {
        // Get badges to evaluate - use chunk's assigned badgeIds, not progress->badge_id
        $badges = ! empty($this->badgeIds)
            ? Badge::whereIn('id', $this->badgeIds)->where('is_active', true)->get()
            : $service->getBadgesToEvaluate($progress->badge_id);

        if ($badges->isEmpty()) {
            $progress->incrementChunkProgress(count($this->userIds), 0, 0, 0);

            return;
        }

        // Get users for this chunk
        $users = User::whereIn('id', $this->userIds)->get();

        if ($users->isEmpty()) {
            $progress->incrementChunkProgress(0, 0, 0, 0);

            return;
        }

        // Preload user badges for efficiency
        $userBadgesMap = $service->preloadUserBadges($this->userIds, $this->badgeIds);

        $awarded = 0;
        $revoked = 0;
        $skipped = 0;

        foreach ($users as $user) {
            foreach ($badges as $badge) {
                try {
                    $result = $service->processBadgeForUser(
                        $badge,
                        $user,
                        $this->noRevoke,
                        $userBadgesMap,
                        $this->reapplyActions
                    );

                    match ($result) {
                        'awarded' => $awarded++,
                        'revoked' => $revoked++,
                        'reapplied' => $awarded++, // Count reapplied as awarded for stats
                        default => $skipped++,
                    };
                } catch (Throwable $e) {
                    // Log individual user/badge errors but continue
                    resolve('log')->warning('[Badges] Failed to process badge for user', [
                        'user_id' => $user->id,
                        'badge_id' => $badge->id,
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            }
        }

        // Update progress atomically
        $progress->incrementChunkProgress(count($users), $awarded, $revoked, $skipped);
    }
}
