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

class UserBadgeHelper
{
    /**
     * Get user badges with caching on the model to avoid re-querying.
     *
     * @return Collection<int, UserBadge>
     */
    public static function getUserBadges(User $user, User $actor): Collection
    {
        if ($user->relationLoaded('userBadges')) {
            /** @var Collection<int, UserBadge> $userBadges */
            $userBadges = $user->getRelation('userBadges');

            if ($userBadges->isNotEmpty() && ! $userBadges->first()->relationLoaded('badge')) {
                $userBadges->load('badge');
            }
        } else {
            /** @var Collection<int, UserBadge> $userBadges */
            $userBadges = UserBadge::where('user_id', $user->id)
                ->with('badge')
                ->get();
            $user->setRelation('userBadges', $userBadges);
        }

        return $userBadges->filter(fn (UserBadge $badge) => BadgeVisibility::decoration($actor, $badge));
    }

    /**
     * Get the primary badge for a user.
     */
    public static function getPrimaryBadge(User $user, User $actor): ?UserBadge
    {
        $userBadges = static::getUserBadges($user, $actor);

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
