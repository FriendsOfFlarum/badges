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
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class ListUserBadgesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $this->prepareDatabase([
            'fof_badges' => [
                ['id' => 100, 'name' => 'First Badge', 'slug' => 'first-badge', 'description' => 'First badge', 'icon' => 'fas fa-star', 'icon_color' => '#ffffff', 'background_color' => '#667eea', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 0, 'trigger_config' => null, 'actions' => null],
                ['id' => 101, 'name' => 'Second Badge', 'slug' => 'second-badge', 'description' => 'Second badge', 'icon' => 'fas fa-trophy', 'icon_color' => '#ffffff', 'background_color' => '#e74c3c', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 1, 'trigger_config' => null, 'actions' => null],
            ],
            User::class => [
                $this->normalUser(),
            ],
            'fof_badge_user' => [
                ['id' => 100, 'user_id' => 2, 'badge_id' => 100, 'granted_by' => 'manual', 'granted_by_user_id' => 1, 'reason' => 'Great work', 'is_seen' => false, 'show_on_card' => true, 'is_primary' => false, 'earned_at' => '2026-01-01 00:00:00'],
                ['id' => 101, 'user_id' => 2, 'badge_id' => 101, 'granted_by' => 'manual', 'granted_by_user_id' => 1, 'reason' => 'Nice job', 'is_seen' => false, 'show_on_card' => true, 'is_primary' => false, 'earned_at' => '2026-01-02 00:00:00'],
                ['id' => 102, 'user_id' => 1, 'badge_id' => 100, 'granted_by' => 'manual', 'granted_by_user_id' => 1, 'reason' => 'Admin badge', 'is_seen' => false, 'show_on_card' => true, 'is_primary' => false, 'earned_at' => '2026-01-01 00:00:00'],
            ],
            'group_permission' => [
                ['group_id' => 1, 'permission' => 'badges.moderate'],
                ['group_id' => 3, 'permission' => 'badges.viewUserBadges'],
                ['group_id' => 3, 'permission' => 'badges.viewList'],
            ],
        ]);
    }

    #[Test]
    public function can_list_user_badges_by_user_filter(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/user-badges', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'filter' => ['user' => 2],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertCount(2, $body['data']);
    }

    #[Test]
    public function user_badges_include_badge_relationship(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/user-badges', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'filter' => ['user' => 2],
                'include' => 'badge',
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $included = collect($body['included'] ?? []);
        $badges = $included->where('type', 'badges');

        $this->assertGreaterThan(0, $badges->count());
    }

    #[Test]
    public function response_includes_pagination_links(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/user-badges', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'filter' => ['user' => 2],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        // Flarum 2.x paginated responses include links
        $this->assertArrayHasKey('links', $body);
    }
}
