<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Event;

use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\UserBadge;

class BadgeAwarded
{
    public User $user;
    public Badge $badge;
    public UserBadge $userBadge;
    public string $grantedBy;
    public ?User $grantedByUser;

    public function __construct(
        User $user,
        Badge $badge,
        UserBadge $userBadge,
        string $grantedBy,
        ?User $grantedByUser = null
    ) {
        $this->user = $user;
        $this->badge = $badge;
        $this->userBadge = $userBadge;
        $this->grantedBy = $grantedBy;
        $this->grantedByUser = $grantedByUser;
    }
}
