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

class DeleteBadgeTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $this->prepareDatabase([
            'fof_badges' => [
                ['id' => 100, 'name' => 'Test Badge', 'slug' => 'test-badge', 'description' => 'A test badge', 'icon' => 'fas fa-star', 'icon_color' => '#ffffff', 'background_color' => '#667eea', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 0, 'trigger_config' => null, 'actions' => null],
                ['id' => 101, 'name' => 'Another Badge', 'slug' => 'another-badge', 'description' => 'Another badge', 'icon' => 'fas fa-trophy', 'icon_color' => '#ffffff', 'background_color' => '#e74c3c', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 1, 'trigger_config' => null, 'actions' => null],
            ],
            'users' => [
                $this->normalUser(),
            ],
            'fof_badge_user' => [
                ['id' => 100, 'user_id' => 2, 'badge_id' => 101, 'granted_by' => 'manual', 'granted_by_user_id' => 1, 'reason' => 'Test', 'is_seen' => false, 'show_on_card' => true, 'is_primary' => false, 'earned_at' => '2026-01-01 00:00:00'],
            ],
            'group_permission' => [
                ['group_id' => 1, 'permission' => 'badges.moderate'],
            ],
        ]);
    }

    /** @test */
    public function guest_cannot_delete_badge(): void
    {
        $response = $this->send(
            $this->request('DELETE', '/api/badges/100')
        );

        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    /** @test */
    public function normal_user_cannot_delete_badge(): void
    {
        $response = $this->send(
            $this->request('DELETE', '/api/badges/100', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function admin_can_delete_badge(): void
    {
        $response = $this->send(
            $this->request('DELETE', '/api/badges/100', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        // Verify badge is gone
        $listResponse = $this->send(
            $this->request('GET', '/api/badges', [
                'authenticatedAs' => 1,
            ])
        );

        $body = json_decode($listResponse->getBody()->getContents(), true);
        $slugs = array_column(array_column($body['data'], 'attributes'), 'slug');

        $this->assertNotContains('test-badge', $slugs);
    }

    /** @test */
    public function deleting_badge_removes_user_badges(): void
    {
        // Badge 101 has a user_badge assigned to user 2
        $response = $this->send(
            $this->request('DELETE', '/api/badges/101', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        // Verify the user_badge record is gone (cascade delete)
        // List user badges for user 2
        $listResponse = $this->send(
            $this->request('GET', '/api/user-badges', [
                'authenticatedAs' => 1,
            ])->withQueryParams([
                'filter' => ['user' => 2],
            ])
        );

        $this->assertEquals(200, $listResponse->getStatusCode());

        $body = json_decode($listResponse->getBody()->getContents(), true);

        // Check that no user badge references badge_id 101
        $badgeIds = [];
        foreach ($body['data'] as $userBadge) {
            $badgeRelation = $userBadge['relationships']['badge']['data'] ?? null;
            if ($badgeRelation) {
                $badgeIds[] = (int) $badgeRelation['id'];
            }
        }

        $this->assertNotContains(101, $badgeIds);
    }
}
