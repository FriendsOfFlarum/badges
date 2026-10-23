<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Psr\Http\Message\ServerRequestInterface;
use WeakMap;

class UserBadgeHelper
{
    /**
     * @var WeakMap<ServerRequestInterface, UserBadgeBatch>|null
     */
    private static ?WeakMap $batches = null;

    /**
     * The batch must not retain its request: aborted serialization should release
     * queued models when the request is released, including in long-lived workers.
     */
    public static function forRequest(ServerRequestInterface $request): UserBadgeBatch
    {
        self::$batches ??= new WeakMap();

        return self::$batches[$request] ??= new UserBadgeBatch();
    }

    /**
     * Cache raw relations on the model; apply actor visibility on every read.
     *
     * @return Collection<int, UserBadge>
     */
    public static function getUserBadges(User $user, User $actor, ?UserBadgeBatch $batch = null): Collection
    {
        $batch ??= new UserBadgeBatch();
        $batch->queue($user);
        $batch->load();

        /** @var Collection<int, UserBadge> $userBadges */
        $userBadges = $user->getRelation('userBadges');

        return $userBadges->filter(fn (UserBadge $badge) => BadgeVisibility::decoration($actor, $badge));
    }

    /**
     * Get the primary badge for a user.
     */
    public static function getPrimaryBadge(User $user, User $actor, ?UserBadgeBatch $batch = null): ?UserBadge
    {
        $userBadges = static::getUserBadges($user, $actor, $batch);

        if ($userBadges->isEmpty()) {
            return null;
        }

        /** @var UserBadge|null $primaryBadge */
        $primaryBadge = $userBadges->firstWhere('is_primary', true);

        if (! $primaryBadge) {
            /** @var UserBadge|null $primaryBadge */
            $primaryBadge = $userBadges
                ->filter(fn (UserBadge $ub) => $ub->badge !== null)
                ->sortBy(fn (UserBadge $ub) => $ub->badge->earned_count ?? PHP_INT_MAX)
                ->first();
        }

        return $primaryBadge;
    }
}
