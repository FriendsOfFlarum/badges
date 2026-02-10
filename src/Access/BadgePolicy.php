<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use FoF\Badges\Badge;

class BadgePolicy extends AbstractPolicy
{
    /**
     * Check if the user can view any badges.
     */
    public function viewAny(User $actor): ?string
    {
        if ($actor->hasPermission('badges.viewList')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can view a specific badge.
     */
    public function view(User $actor, Badge $badge): ?string
    {
        // Hidden badges require moderate permission
        if (!$badge->is_visible && !$actor->hasPermission('badges.moderate')) {
            return $this->deny();
        }

        if ($actor->hasPermission('badges.viewList')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can create badges.
     */
    public function create(User $actor): ?string
    {
        if ($actor->hasPermission('badges.moderate')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can edit a badge.
     */
    public function edit(User $actor, Badge $badge): ?string
    {
        if ($actor->hasPermission('badges.moderate')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can delete a badge.
     */
    public function delete(User $actor, Badge $badge): ?string
    {
        if ($actor->hasPermission('badges.moderate')) {
            return $this->allow();
        }

        return null;
    }
}
