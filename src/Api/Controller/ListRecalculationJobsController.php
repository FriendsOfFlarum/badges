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

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use FoF\Badges\BadgeRecalculationProgress;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListRecalculationJobsController implements RequestHandlerInterface
{
    /**
     * Jobs that haven't been updated in this many minutes are considered stuck.
     */
    protected int $stuckThresholdMinutes = 5;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $jobs = BadgeRecalculationProgress::query()
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        $formattedJobs = $jobs->map(function (BadgeRecalculationProgress $job) {
            return $this->formatJob($job);
        });

        return new JsonResponse([
            'jobs' => $formattedJobs,
        ]);
    }

    protected function formatJob(BadgeRecalculationProgress $job): array
    {
        $isStuck = $this->isJobStuck($job);

        return [
            'id' => $job->id,
            'status' => $job->status,
            'isStuck' => $isStuck,
            'totalUsers' => (int) $job->total_users,
            'processedUsers' => (int) $job->processed_users,
            'totalBadges' => (int) $job->total_badges,
            'awarded' => (int) $job->awarded,
            'revoked' => (int) $job->revoked,
            'skipped' => (int) $job->skipped,
            'percentage' => $job->getProgressPercentage(),
            'startedAt' => $job->started_at?->toIso8601String(),
            'completedAt' => $job->completed_at?->toIso8601String(),
            'createdAt' => $job->created_at?->toIso8601String(),
            'updatedAt' => $job->updated_at?->toIso8601String(),
            'errorMessage' => $job->error_message,
        ];
    }

    protected function isJobStuck(BadgeRecalculationProgress $job): bool
    {
        if ($job->status !== BadgeRecalculationProgress::STATUS_RUNNING) {
            return false;
        }

        $lastUpdate = $job->updated_at ?? $job->created_at;

        if (!$lastUpdate) {
            return false;
        }

        return $lastUpdate->diffInMinutes(Carbon::now()) >= $this->stuckThresholdMinutes;
    }
}
