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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadgeVisibility
{
    public static function manages(User $actor): bool
    {
        return $actor->hasPermission('badges.moderate') || $actor->hasPermission('badges.giveManually');
    }

    /**
     * @param Builder<UserBadge> $query
     */
    public static function userBadges($query, User $actor): void
    {
        if (self::manages($actor)) {
            return;
        }

        $query->where(function ($query) use ($actor) {
            $query->whereRaw('1 = 0');
            if (! $actor->isGuest()) {
                $query->orWhere('user_id', $actor->id);
            }
            if ($actor->can('viewAny', UserBadge::class)) {
                $query->orWhere('show_on_card', true);
            }
        });
    }

    public static function decoration(User $actor, UserBadge $badge): bool
    {
        return $badge->show_on_card && $badge->badge !== null
            && (self::manages($actor) || (! $actor->isGuest() && (int) $actor->id === (int) $badge->user_id)
                || $actor->can('viewAny', UserBadge::class));
    }

    /**
     * @param Builder<Badge>|HasMany<Badge> $query
     */
    public static function catalog($query, User $actor): void
    {
        if (! $actor->hasPermission('badges.moderate')) {
            $query->where('is_active', true)->where('is_visible', true);
        }
    }
}
