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

use FoF\Badges\BadgeCategory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BadgeCategoryModelTest extends TestCase
{
    #[Test]
    public function test_build_creates_category_with_defaults(): void
    {
        $category = BadgeCategory::build('Achievement', 'achievement', 'Achievement badges');

        $this->assertEquals('Achievement', $category->name);
        $this->assertEquals('achievement', $category->slug);
        $this->assertEquals('Achievement badges', $category->description);
        $this->assertTrue($category->is_enabled);
        $this->assertEquals(0, $category->order);
    }
}
