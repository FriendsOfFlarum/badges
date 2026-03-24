<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Event;

use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\UserBadge;

class BadgeAwarded
{
    public function __construct(public User $user, public Badge $badge, public UserBadge $userBadge, public string $grantedBy, public ?User $grantedByUser = null)
    {
    }
}
