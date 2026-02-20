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

class BadgeRevoked
{
    public User $user;
    public Badge $badge;
    public string $reason;

    public function __construct(User $user, Badge $badge, string $reason = 'manual')
    {
        $this->user = $user;
        $this->badge = $badge;
        $this->reason = $reason;
    }
}
