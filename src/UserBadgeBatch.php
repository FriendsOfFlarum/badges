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

class UserBadgeBatch
{
    /** @var array<int, User> */
    private array $queued = [];

    public function queue(User $user): void
    {
        // Loaded collections can still contain badges with missing relations.
        $this->queued[spl_object_id($user)] = $user;
    }

    public function load(): void
    {
        $users = $this->queued;
        $this->queued = [];
        $missing = array_filter($users, fn (User $user) => ! $user->relationLoaded('userBadges'));

        if ($missing !== []) {
            $byUser = UserBadge::query()
                ->whereIn('user_id', array_unique(array_map(fn (User $user) => $user->id, $missing)))
                ->get()
                ->groupBy('user_id');

            foreach ($missing as $user) {
                $user->setRelation('userBadges', new Collection($byUser->get($user->id)?->all() ?? []));
            }
        }

        // Combine all raw collections, including deliberately preloaded subsets.
        // Object identity preserves distinct instances of the same database row.
        /** @var array<int, UserBadge> $badges */
        $badges = [];

        foreach ($users as $user) {
            /** @var Collection<int, UserBadge> $userBadges */
            $userBadges = $user->getRelation('userBadges');

            foreach ($userBadges as $badge) {
                $badges[spl_object_id($badge)] = $badge;
            }
        }

        // One nested query for the whole batch; known relations (even null) win.
        (new Collection(array_values($badges)))->loadMissing('badge');
    }
}
