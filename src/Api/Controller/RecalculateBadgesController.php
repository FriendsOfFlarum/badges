<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\User;
use FoF\Badges\BadgeRecalculationProgress;
use FoF\Badges\Job\RecalculateBadgesJob;
use FoF\Badges\Service\BadgeRecalculationService;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RecalculateBadgesController implements RequestHandlerInterface
{
    protected Queue $queue;
    protected BadgeRecalculationService $service;

    public function __construct(Queue $queue, BadgeRecalculationService $service)
    {
        $this->queue = $queue;
        $this->service = $service;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        $body = $request->getParsedBody();
        $badgeId = Arr::get($body, 'badgeId');
        $userId = Arr::get($body, 'userId');
        $noRevoke = (bool) Arr::get($body, 'noRevoke', false);
        $chunkSize = (int) Arr::get($body, 'chunkSize', 100);
        $reapplyActions = (bool) Arr::get($body, 'reapplyActions', false);

        // Validate chunk size (min 100, max 2000)
        $chunkSize = max(100, min(2000, $chunkSize));

        // For single user recalculation, only need giveManually permission
        // For full recalculation, need admin
        if ($userId) {
            $actor->assertCan('badges.giveManually');
        } else {
            $actor->assertAdmin();
        }

        // For single user, do it synchronously without progress tracking
        if ($userId) {
            return $this->recalculateSingleUser((int) $userId, $badgeId ? (int) $badgeId : null, $noRevoke, $reapplyActions);
        }

        // Check for active jobs only for full recalculation
        if (BadgeRecalculationProgress::hasActiveJob()) {
            $activeJob = BadgeRecalculationProgress::getActiveJob();

            return new JsonResponse([
                'success' => false,
                'error' => 'already_running',
                'message' => 'A badge recalculation is already in progress.',
                'progress' => $this->formatProgress($activeJob),
            ], 409);
        }

        // Full recalculation with queue
        $progress = BadgeRecalculationProgress::createNew(
            $actor,
            $badgeId ? (int) $badgeId : null,
            $noRevoke,
            $chunkSize,
            $reapplyActions
        );

        $this->queue->push(new RecalculateBadgesJob($progress->id));

        return new JsonResponse([
            'success' => true,
            'message' => 'Badge recalculation started.',
            'progressId' => $progress->id,
            'progress' => $this->formatProgress($progress),
        ]);
    }

    protected function recalculateSingleUser(int $userId, ?int $badgeId, bool $noRevoke, bool $reapplyActions = false): JsonResponse
    {
        $user = User::findOrFail($userId);

        $badges = $this->service->getBadgesToEvaluate($badgeId, $reapplyActions);
        $badgeIds = $badges->pluck('id')->toArray();
        $userBadgesMap = $this->service->preloadUserBadges([$userId], $badgeIds);

        $awarded = 0;
        $revoked = 0;
        $skipped = 0;
        $reapplied = 0;

        foreach ($badges as $badge) {
            $result = $this->service->processBadgeForUser($badge, $user, $noRevoke, $userBadgesMap, $reapplyActions);

            match ($result) {
                'awarded' => $awarded++,
                'revoked' => $revoked++,
                'reapplied' => $reapplied++,
                default => $skipped++,
            };
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Badge recalculation completed for user.',
            'awarded' => $awarded,
            'revoked' => $revoked,
            'reapplied' => $reapplied,
            'skipped' => $skipped,
        ]);
    }

    protected function formatProgress(BadgeRecalculationProgress $progress): array
    {
        return [
            'id' => $progress->id,
            'status' => $progress->status,
            'totalUsers' => $progress->total_users,
            'processedUsers' => $progress->processed_users,
            'totalBadges' => $progress->total_badges,
            'awarded' => $progress->awarded,
            'revoked' => $progress->revoked,
            'skipped' => $progress->skipped,
            'percentage' => $progress->getProgressPercentage(),
            'totalChunks' => $progress->total_chunks,
            'processedChunks' => $progress->processed_chunks,
            'chunkPercentage' => $progress->getChunkProgressPercentage(),
            'chunkSize' => $progress->chunk_size,
            'startedAt' => $progress->started_at?->toIso8601String(),
            'completedAt' => $progress->completed_at?->toIso8601String(),
            'errorMessage' => $progress->error_message,
        ];
    }
}
