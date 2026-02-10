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
use FoF\Badges\BadgeRecalculationProgress;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RecalculationStatusController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $queryParams = $request->getQueryParams();
        $progressId = Arr::get($queryParams, 'id');

        if ($progressId) {
            $progress = BadgeRecalculationProgress::find($progressId);
        } else {
            $progress = BadgeRecalculationProgress::getActiveJob()
                ?? BadgeRecalculationProgress::getLatest();
        }

        if (!$progress) {
            return new JsonResponse([
                'hasProgress' => false,
                'progress' => null,
            ]);
        }

        return new JsonResponse([
            'hasProgress' => true,
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
