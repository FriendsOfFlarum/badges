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
use FoF\Badges\BadgeCategory;

class BadgeCategoryPolicy extends AbstractPolicy
{
    /**
     * Check if the user can view any categories.
     */
    public function viewAny(User $actor): ?string
    {
        if ($actor->hasPermission('badges.viewList')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can view a specific category.
     */
    public function view(User $actor, BadgeCategory $category): ?string
    {
        // Disabled categories require moderate permission
        if (!$category->is_enabled && !$actor->hasPermission('badges.moderate')) {
            return $this->deny();
        }

        if ($actor->hasPermission('badges.viewList')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can create categories.
     */
    public function create(User $actor): ?string
    {
        if ($actor->hasPermission('badges.moderate')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can edit a category.
     */
    public function edit(User $actor, BadgeCategory $category): ?string
    {
        if ($actor->hasPermission('badges.moderate')) {
            return $this->allow();
        }

        return null;
    }

    /**
     * Check if the user can delete a category.
     */
    public function delete(User $actor, BadgeCategory $category): ?string
    {
        if ($actor->hasPermission('badges.moderate')) {
            return $this->allow();
        }

        return null;
    }
}
