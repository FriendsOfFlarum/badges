<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'badges.viewList' => Group::GUEST_ID,
    'badges.viewUserBadges' => Group::GUEST_ID,
    'badges.moderate' => Group::MODERATOR_ID,
    'badges.giveManually' => Group::MODERATOR_ID,
]);
