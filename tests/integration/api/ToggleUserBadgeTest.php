<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class ToggleUserBadgeTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $this->prepareDatabase([
            'fof_badges' => [
                ['id' => 100, 'name' => 'Test Badge', 'slug' => 'test-badge', 'description' => 'A test badge', 'icon' => 'fas fa-star', 'icon_color' => '#ffffff', 'background_color' => '#667eea', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 0, 'trigger_config' => null, 'actions' => null],
                ['id' => 101, 'name' => 'Second Badge', 'slug' => 'second-badge', 'description' => 'A second badge', 'icon' => 'fas fa-trophy', 'icon_color' => '#ffffff', 'background_color' => '#e74c3c', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 1, 'trigger_config' => null, 'actions' => null],
            ],
            User::class => [
                $this->normalUser(),
            ],
            'fof_badge_user' => [
                ['id' => 100, 'user_id' => 2, 'badge_id' => 100, 'granted_by' => 'manual', 'granted_by_user_id' => 1, 'reason' => 'Great work', 'is_seen' => false, 'show_on_card' => true, 'is_primary' => false, 'earned_at' => '2026-01-01 00:00:00'],
                ['id' => 101, 'user_id' => 2, 'badge_id' => 101, 'granted_by' => 'manual', 'granted_by_user_id' => 1, 'reason' => 'Nice job', 'is_seen' => false, 'show_on_card' => true, 'is_primary' => true, 'earned_at' => '2026-01-02 00:00:00'],
                ['id' => 102, 'user_id' => 1, 'badge_id' => 100, 'granted_by' => 'manual', 'granted_by_user_id' => 1, 'reason' => 'Admin badge', 'is_seen' => false, 'show_on_card' => true, 'is_primary' => false, 'earned_at' => '2026-01-01 00:00:00'],
            ],
        ]);
    }

    #[Test]
    public function owner_can_toggle_favorite(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/user-badges/100/toggle', [
                'authenticatedAs' => 2,
                'json' => [
                    'action' => 'toggleFavorite',
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertTrue($body['is_primary']);
    }

    #[Test]
    public function owner_can_toggle_visibility(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/user-badges/100/toggle', [
                'authenticatedAs' => 2,
                'json' => [
                    'action' => 'toggleVisibility',
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertFalse($body['show_on_card']);
    }

    #[Test]
    public function non_owner_cannot_toggle_badge(): void
    {
        // User 1 (admin) tries to toggle user 2's badge
        $response = $this->send(
            $this->request('POST', '/api/user-badges/100/toggle', [
                'authenticatedAs' => 1,
                'json' => [
                    'action' => 'toggleFavorite',
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function cannot_hide_favorite_badge(): void
    {
        // User badge 101 is already primary/favorite for user 2
        $response = $this->send(
            $this->request('POST', '/api/user-badges/101/toggle', [
                'authenticatedAs' => 2,
                'json' => [
                    'action' => 'toggleVisibility',
                ],
            ])
        );

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('error', $body);
    }

    #[Test]
    public function invalid_action_returns_error(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/user-badges/100/toggle', [
                'authenticatedAs' => 2,
                'json' => [
                    'action' => 'invalidAction',
                ],
            ])
        );

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('error', $body);
    }
}
