<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Api\Controller;

use Flarum\Http\RequestUtil;
use FoF\Badges\BadgeRecalculationProgress;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CancelRecalculationController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $body = $request->getParsedBody();
        $queryParams = $request->getQueryParams();

        // Support both query param and body for id
        $progressId = Arr::get($body, 'id') ?? Arr::get($queryParams, 'id');
        $force = (bool) Arr::get($body, 'force', false);
        $markFailed = (bool) Arr::get($body, 'markFailed', false);

        if ($progressId) {
            $progress = BadgeRecalculationProgress::find($progressId);
        } else {
            $progress = BadgeRecalculationProgress::getActiveJob();
        }

        if (! $progress) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No active recalculation found.',
            ], 404);
        }

        // Force cancel allows cancelling stuck jobs regardless of status
        if (! $force && ! $progress->isRunning() && ! $progress->isPending()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Recalculation is not running. Use force=true to cancel anyway.',
            ], 400);
        }

        if ($markFailed) {
            $progress->markAsFailed('Manually marked as failed by administrator');
        } else {
            $progress->markAsCancelled();
        }

        return new JsonResponse([
            'success' => true,
            'message' => $markFailed ? 'Recalculation marked as failed.' : 'Recalculation cancelled.',
            'progress' => $this->formatProgress($progress),
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
            'startedAt' => $progress->started_at?->toIso8601String(),
            'completedAt' => $progress->completed_at?->toIso8601String(),
            'errorMessage' => $progress->error_message,
        ];
    }
}
