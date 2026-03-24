<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Tests\Integration\Api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Flarum\User\User;

class ShowBadgeTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $this->prepareDatabase([
            'fof_badge_cat' => [
                ['id' => 100, 'name' => 'Achievement', 'slug' => 'achievement', 'description' => 'Achievement badges', 'is_enabled' => true, 'order' => 0],
            ],
            'fof_badges' => [
                ['id' => 100, 'name' => 'Visible Badge', 'slug' => 'visible-badge', 'description' => 'A visible badge', 'icon' => 'fas fa-star', 'icon_color' => '#ffffff', 'background_color' => '#667eea', 'category_id' => 100, 'is_active' => true, 'is_visible' => true, 'order' => 0, 'trigger_config' => null, 'actions' => null],
                ['id' => 101, 'name' => 'Hidden Badge', 'slug' => 'hidden-badge', 'description' => 'A hidden badge', 'icon' => 'fas fa-eye-slash', 'icon_color' => '#ffffff', 'background_color' => '#333333', 'category_id' => 100, 'is_active' => true, 'is_visible' => false, 'order' => 1, 'trigger_config' => null, 'actions' => null],
            ],
            User::class => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => 1, 'permission' => 'badges.moderate'],
                ['group_id' => 3, 'permission' => 'badges.viewList'],
            ],
        ]);
    }

    #[Test]
    public function guest_can_view_visible_badge(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badges/100')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('Visible Badge', $body['data']['attributes']['name']);
        $this->assertEquals('visible-badge', $body['data']['attributes']['slug']);
        $this->assertEquals('fas fa-star', $body['data']['attributes']['icon']);
        $this->assertTrue($body['data']['attributes']['isVisible']);
    }

    #[Test]
    public function guest_cannot_view_hidden_badge(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badges/101')
        );

        // Policy denies viewing hidden badges for non-moderators
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    #[Test]
    public function admin_can_view_hidden_badge(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badges/101', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('Hidden Badge', $body['data']['attributes']['name']);
        $this->assertFalse($body['data']['attributes']['isVisible']);
    }

    #[Test]
    public function show_badge_includes_category(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badges/100')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        // The ShowBadgeController includes 'category' by default
        $included = collect($body['included'] ?? []);
        $category = $included->firstWhere('type', 'badge-categories');

        $this->assertNotNull($category);
        $this->assertEquals('Achievement', $category['attributes']['name']);
    }
}
