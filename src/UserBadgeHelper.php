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
     * Users whose badges will be read soon.
     *
     * @var array<int, User>
     */
    private static array $queued = [];

    /**
     * Read a user's badges later, together with every other user queued before
     * the first one is read.
     *
     * Users reach a page through other extensions' relationships too (the
     * author of a moderator note, the uploader of a file), where no eager load
     * of ours can follow, and each cost a query. Queued, the page's users load
     * in one query, plus one for the badges themselves.
     */
    public static function queue(User $user): void
    {
        if (! $user->relationLoaded('userBadges')) {
            self::$queued[spl_object_id($user)] = $user;
        }
    }

    /**
     * Forget every queued user, for tests. A page whose serialization fails
     * part-way leaves its users queued; the next read loads them along with
     * its own and they are discarded with the request, so nothing is wrong,
     * only a few rows wasted.
     */
    public static function resetQueue(): void
    {
        self::$queued = [];
    }

    private static function loadQueued(): void
    {
        $users = array_filter(self::$queued, fn (User $user) => ! $user->relationLoaded('userBadges'));
        self::$queued = [];

        if ($users === []) {
            return;
        }

        $byUser = UserBadge::query()
            ->whereIn('user_id', array_unique(array_map(fn (User $user) => $user->id, $users)))
            ->with('badge')
            ->get()
            ->groupBy('user_id');

        foreach ($users as $user) {
            $user->setRelation('userBadges', new Collection($byUser->get($user->id)?->all() ?? []));
        }
    }

    /**
     * Get user badges with caching on the model to avoid re-querying.
     *
     * @return Collection<int, UserBadge>
     */
    public static function getUserBadges(User $user): Collection
    {
        if ($user->relationLoaded('userBadges')) {
            /** @var Collection<int, UserBadge> $userBadges */
            $userBadges = $user->getRelation('userBadges');

            if ($userBadges->isNotEmpty() && ! $userBadges->first()->relationLoaded('badge')) {
                $userBadges->load('badge');
            }
        } else {
            // With everyone else queued so far, in one query.
            self::$queued[spl_object_id($user)] = $user;
            self::loadQueued();

            /** @var Collection<int, UserBadge> $userBadges */
            $userBadges = $user->getRelation('userBadges');
        }

        return $userBadges;
    }

    /**
     * Get the primary badge for a user.
     */
    public static function getPrimaryBadge(User $user): ?UserBadge
    {
        $userBadges = static::getUserBadges($user);

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
