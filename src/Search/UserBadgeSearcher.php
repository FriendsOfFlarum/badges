<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Search;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use FoF\Badges\UserBadge;
use Illuminate\Database\Eloquent\Builder;

class UserBadgeSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        return UserBadge::query()->select('fof_badge_user.*');
    }
}
