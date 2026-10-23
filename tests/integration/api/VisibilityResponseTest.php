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
use FoF\Badges\BadgeCategory;

/** Full HTTP regressions; execution requires an isolated supported test database. */
class VisibilityResponseTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-badges');
        $this->prepareDatabase([
            'users' => [$this->normalUser(), array_merge($this->normalUser(), [
                'id' => 3, 'username' => 'other', 'email' => 'other@example.test',
            ])],
            'fof_badge_cat' => [
                ['id' => 100, 'name' => 'Enabled', 'slug' => 'enabled', 'is_enabled' => true],
                ['id' => 101, 'name' => 'Disabled', 'slug' => 'disabled', 'is_enabled' => false],
            ],
            'fof_badges' => [
                ['id' => 100, 'name' => 'Public', 'slug' => 'public', 'category_id' => 100, 'is_active' => true, 'is_visible' => true],
                ['id' => 101, 'name' => 'Secret', 'slug' => 'secret', 'category_id' => 100, 'is_active' => true, 'is_visible' => false],
                ['id' => 102, 'name' => 'Inactive', 'slug' => 'inactive', 'category_id' => 100, 'is_active' => false, 'is_visible' => true],
                ['id' => 103, 'name' => 'Disabled category badge', 'slug' => 'disabled-category-badge', 'category_id' => 101, 'is_active' => true, 'is_visible' => true],
            ],
            'fof_badge_user' => [
                ['id' => 100, 'user_id' => 2, 'badge_id' => 100, 'show_on_card' => false, 'is_primary' => true, 'reason' => 'PRIVATE REASON', 'earned_at' => '2026-01-01 00:00:00'],
                ['id' => 101, 'user_id' => 2, 'badge_id' => 101, 'show_on_card' => true, 'is_primary' => false, 'reason' => 'Public reason', 'earned_at' => '2026-01-02 00:00:00'],
            ],
        ]);
    }

    public static function categoryActors(): array
    {
        $cases = [];
        foreach ([false, true] as $preloaded) {
            foreach ([null, 3, 1] as $actor) {
                $cases[($preloaded ? 'preloaded' : 'unloaded').'-'.($actor ?? 'guest')] = [$preloaded, $actor];
            }
        }

        return $cases;
    }

    /** @dataProvider categoryActors */
    public function test_category_includes_and_counts_match_visibility(bool $preloaded, ?int $actor): void
    {
        $this->prepareDatabase(['group_permission' => [
            ['group_id' => 2, 'permission' => 'badges.viewList'],
            ['group_id' => 3, 'permission' => 'badges.viewList'],
        ]]);
        $this->app();
        $observed = [];
        BadgeCategory::retrieved(function (BadgeCategory $category) use ($preloaded, &$observed) {
            if ($preloaded) {
                $category->load('badges');
            }
            $observed[] = $category->relationLoaded('badges');
        });
        $body = $this->get('/api/badge-categories', $actor, ['include' => 'badges']);
        $this->assertNotEmpty($observed);
        $this->assertSame([$preloaded], array_values(array_unique($observed)));
        $categories = array_column($body['data'], null, 'id');
        $expected = $actor === 1 ? ['100', '101', '102'] : ['100'];
        $this->assertEqualsCanonicalizing($expected, array_column($categories[100]['relationships']['badges']['data'], 'id'));
        $this->assertSame(count($expected), $categories[100]['attributes']['badgeCount']);
        $included = array_values(array_filter($body['included'] ?? [], fn ($item) => $item['type'] === 'badges'));
        $this->assertEqualsCanonicalizing($actor === 1 ? ['100', '101', '102', '103'] : ['100'], array_column($included, 'id'));
        $this->assertSame($actor === 1, isset($categories[101]));
    }

    public static function privacyActors(): array
    {
        return [
            'owner without permission' => [2, false, ['100', '101'], true],
            'stranger without permission' => [3, false, [], false],
            'stranger with permission' => [3, true, ['101'], true],
            'guest with permission' => [null, true, ['101'], true],
            'administrator' => [1, false, ['100', '101'], true],
        ];
    }

    /** @dataProvider privacyActors */
    public function test_final_responses_respect_privacy(?int $actor, bool $permission, array $ids, bool $decoration): void
    {
        $this->database()->table('group_permission')->where('permission', 'badges.viewUserBadges')->delete();
        if ($permission) {
            $this->database()->table('group_permission')->insert([
                ['group_id' => 2, 'permission' => 'badges.viewUserBadges'],
                ['group_id' => 3, 'permission' => 'badges.viewUserBadges'],
            ]);
        }
        $body = $this->get('/api/user-badges', $actor, ['filter' => ['user' => 2], 'include' => 'badge,user']);
        $this->assertEqualsCanonicalizing($ids, array_column($body['data'], 'id'));
        if (! in_array('100', $ids, true)) {
            $this->assertStringNotContainsString('PRIVATE REASON', json_encode($body));
        }
        $badges = array_values(array_filter($body['included'] ?? [], fn ($item) => $item['type'] === 'badges'));
        $this->assertEqualsCanonicalizing($ids, array_column($badges, 'id'));
        foreach ($badges as $badge) {
            if ($actor !== 1) {
                $this->assertArrayNotHasKey('actions', $badge['attributes']);
                $this->assertArrayNotHasKey('triggerConfig', $badge['attributes']);
            }
        }
        $profile = $this->get('/api/users/2', $actor)['data']['attributes'];
        $this->assertSame($decoration ? 1 : 0, $profile['badgeCount']);
        $this->assertSame($decoration ? 'Secret' : null, $profile['primaryBadgeName']);
        $this->assertSame($decoration ? 'fas fa-award' : null, $profile['primaryBadgeIcon']);
        $this->assertSame($decoration ? [['name' => 'Secret', 'icon' => 'fas fa-award']] : null, $profile['visibleBadges']);
    }

    private function get(string $url, ?int $actor, array $query = []): array
    {
        $response = $this->send($this->request('GET', $url, $actor === null ? [] : ['authenticatedAs' => $actor])->withQueryParams($query));
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $response->getStatusCode(), json_encode($body));

        return $body;
    }
}
