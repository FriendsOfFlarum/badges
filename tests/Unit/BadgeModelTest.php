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

use FoF\Badges\Badge;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BadgeModelTest extends TestCase
{
    #[Test]
    public function test_build_creates_badge_with_defaults(): void
    {
        $badge = Badge::build('Test Badge', 'test-badge', 'A test badge description');

        $this->assertEquals('Test Badge', $badge->name);
        $this->assertEquals('test-badge', $badge->slug);
        $this->assertEquals('A test badge description', $badge->description);
        $this->assertEquals('fas fa-award', $badge->icon);
        $this->assertEquals('#ffffff', $badge->icon_color);
        $this->assertEquals('#667eea', $badge->background_color);
        $this->assertTrue($badge->is_active);
        $this->assertTrue($badge->is_visible);
        $this->assertEquals(0, $badge->earned_count);
        $this->assertEquals(0, $badge->order);
    }

    #[Test]
    public function test_is_manual_returns_true_when_no_trigger_config(): void
    {
        $badge = Badge::build('Manual Badge', 'manual-badge');
        $badge->trigger_config = null;

        $this->assertTrue($badge->isManual());
        $this->assertFalse($badge->isAutomatic());
    }

    #[Test]
    public function test_is_automatic_returns_true_when_trigger_config_set(): void
    {
        $badge = Badge::build('Auto Badge', 'auto-badge');
        $badge->trigger_config = [
            'conditions' => [
                ['metric' => 'post_count', 'operator' => 'gte', 'value' => 10],
            ],
            'logic' => 'and',
        ];

        $this->assertTrue($badge->isAutomatic());
        $this->assertFalse($badge->isManual());
    }
}
