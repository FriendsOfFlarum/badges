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

/**
 * The users included on a list of user badges show their own badges too
 * (badgeCount, primary badge, visibleBadges): those must load once for the
 * page, not once per user.
 */
class ListUserBadgesQueryCountTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const HOLDERS = 8;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $users = $userBadges = [];

        for ($i = 1; $i <= self::HOLDERS; $i++) {
            $users[] = ['id' => $i + 2, 'username' => "holder$i", 'email' => "holder$i@machine.local", 'is_email_confirmed' => 1];
            $userBadges[] = ['id' => $i, 'user_id' => $i + 2, 'badge_id' => 100, 'granted_by' => 'manual', 'granted_by_user_id' => 1, 'reason' => null, 'is_seen' => false, 'show_on_card' => true, 'is_primary' => false, 'earned_at' => '2026-01-01 00:00:00'];

            // Every other holder has a second badge, so the counts tell them apart.
            if ($i % 2) {
                $userBadges[] = ['id' => $i + 100, 'user_id' => $i + 2, 'badge_id' => 101, 'granted_by' => 'manual', 'granted_by_user_id' => 1, 'reason' => null, 'is_seen' => false, 'show_on_card' => true, 'is_primary' => false, 'earned_at' => '2025-01-01 00:00:00'];
            }
        }

        $this->prepareDatabase([
            'fof_badges' => [
                ['id' => 100, 'name' => 'Starter', 'slug' => 'starter', 'description' => 'Starter', 'icon' => 'fas fa-star', 'icon_color' => '#ffffff', 'background_color' => '#667eea', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 0, 'trigger_config' => null, 'actions' => null],
                ['id' => 101, 'name' => 'Veteran', 'slug' => 'veteran', 'description' => 'Veteran', 'icon' => 'fas fa-trophy', 'icon_color' => '#ffffff', 'background_color' => '#e74c3c', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 1, 'trigger_config' => null, 'actions' => null],
            ],
            User::class => array_merge([$this->normalUser()], $users),
            'fof_badge_user' => $userBadges,
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'badges.viewUserBadges'],
                ['group_id' => 3, 'permission' => 'badges.viewList'],
            ],
        ]);
    }

    #[Test]
    public function the_holders_badges_load_once()
    {
        $this->app();
        $db = $this->database();
        $db->enableQueryLog();
        $db->flushQueryLog();

        $response = $this->send(
            $this->request('GET', '/api/user-badges', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['badge' => 100]])
        );

        $sql = array_map(fn ($q) => str_replace(['`', '"'], '', $q), array_column($db->getQueryLog(), 'query'));
        $db->flushQueryLog();

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertCount(self::HOLDERS, $body['data']);

        $perUser = array_filter($sql, fn ($q) => preg_match('/select \* from fof_badge_user where (fof_badge_user\.)?user_id (=|in)/', $q));
        $this->assertLessThanOrEqual(1, count($perUser), "The holders' badges load in one query");

        $counts = [];
        foreach ($body['included'] as $resource) {
            if ($resource['type'] === 'users') {
                $counts[$resource['attributes']['username']] = $resource['attributes']['badgeCount'];
            }
        }

        ksort($counts);
        $this->assertSame(
            ['holder1' => 2, 'holder2' => 1, 'holder3' => 2, 'holder4' => 1, 'holder5' => 2, 'holder6' => 1, 'holder7' => 2, 'holder8' => 1],
            $counts
        );
    }
}
