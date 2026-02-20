<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use FoF\Badges\UserBadge;

class UserBadgePolicy extends AbstractPolicy
{
    /**
     * Check if the user can view any user badges.
     */
    public function viewAny(User $actor): ?string
    {
        if ($actor->hasPermission('badges.viewUserBadges')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can view a specific user badge.
     */
    public function view(User $actor, UserBadge $userBadge): ?string
    {
        // Users can always view their own badges
        if ($actor->id === $userBadge->user_id) {
            return $this->allow();
        }

        // Check if the badge itself is visible
        $badge = $userBadge->badge;
        if (! $badge->is_visible && ! $actor->hasPermission('badges.moderate')) {
            return $this->deny();
        }

        if ($actor->hasPermission('badges.viewUserBadges')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can assign badges (create user badges).
     */
    public function create(User $actor): ?string
    {
        if ($actor->hasPermission('badges.giveManually')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can revoke a badge (delete user badge).
     */
    public function delete(User $actor, UserBadge $userBadge): ?string
    {
        if ($actor->hasPermission('badges.moderate')) {
            return $this->allow();
        }

        return null;
    }
}
