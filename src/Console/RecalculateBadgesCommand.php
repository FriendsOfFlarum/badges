<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Console;

use Flarum\User\User;
use FoF\Badges\Service\BadgeRecalculationService;
use Illuminate\Console\Command;

class RecalculateBadgesCommand extends Command
{
    protected $signature = 'badges:recalculate
                            {--user= : Recalculate for a specific user ID}
                            {--badge= : Recalculate for a specific badge ID}
                            {--no-revoke : Do not revoke badges that no longer qualify}
                            {--chunk=100 : Number of users to process per batch}';

    protected $description = 'Recalculate and award badges based on current trigger conditions';

    protected BadgeRecalculationService $service;

    public function __construct(BadgeRecalculationService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        $userId = $this->option('user');
        $badgeId = $this->option('badge');
        $noRevoke = (bool) $this->option('no-revoke');
        $chunkSize = (int) $this->option('chunk');

        $badges = $this->service->getBadgesToEvaluate($badgeId ? (int) $badgeId : null);

        if ($badges->isEmpty()) {
            $this->warn('No badges with automatic triggers found.');
            return 0;
        }

        $usersQuery = User::query();
        if ($userId) {
            $usersQuery->where('id', $userId);
        }

        $totalUsers = $usersQuery->count();

        if ($totalUsers === 0) {
            $this->error('No users found.');
            return 1;
        }

        $this->info("Processing {$totalUsers} user(s) for {$badges->count()} badge(s)...");

        $awarded = 0;
        $revoked = 0;
        $skipped = 0;
        $badgeIds = $badges->pluck('id')->toArray();

        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        $usersQuery->chunk($chunkSize, function ($users) use ($badges, $badgeIds, $noRevoke, &$awarded, &$revoked, &$skipped, $bar) {
            $userIds = $users->pluck('id')->toArray();
            $userBadgesMap = $this->service->preloadUserBadges($userIds, $badgeIds);

            foreach ($users as $user) {
                foreach ($badges as $badge) {
                    $result = $this->service->processBadgeForUser($badge, $user, $noRevoke, $userBadgesMap);

                    match ($result) {
                        'awarded' => $awarded++,
                        'revoked' => $revoked++,
                        default => $skipped++,
                    };
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Recalculation complete!");
        $this->table(
            ['Action', 'Count'],
            [
                ['Badges Awarded', $awarded],
                ['Badges Revoked', $revoked],
                ['No Change', $skipped],
            ]
        );

        return 0;
    }
}
