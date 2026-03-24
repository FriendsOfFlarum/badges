<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Tests\Unit;

use FoF\Badges\Notification\BadgeEarnedBlueprint;
use FoF\Badges\UserBadge;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class NotificationBlueprintTest extends TestCase
{
    #[Test]
    public function test_get_type_returns_badge_earned(): void
    {
        $this->assertEquals('badgeEarned', BadgeEarnedBlueprint::getType());
    }

    #[Test]
    public function test_get_subject_model_returns_user_badge_class(): void
    {
        $this->assertEquals(UserBadge::class, BadgeEarnedBlueprint::getSubjectModel());
    }
}
