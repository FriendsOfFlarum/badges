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
use FoF\Badges\BadgeCategory;
use PHPUnit\Framework\Attributes\DataProvider;

class VisibilityResponseTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-badges');
        $this->prepareDatabase([
            User::class => [$this->normalUser(), array_merge($this->normalUser(), [
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
            'discussions' => [[
                'id' => 100, 'title' => 'Privacy discussion', 'slug' => 'privacy-discussion',
                'user_id' => 2, 'first_post_id' => 100, 'last_post_id' => 100,
                'last_posted_user_id' => 2, 'comment_count' => 1, 'participant_count' => 1,
                'created_at' => '2026-01-01 00:00:00', 'last_posted_at' => '2026-01-01 00:00:00',
            ]],
            'posts' => [[
                'id' => 100, 'discussion_id' => 100, 'user_id' => 2, 'number' => 1,
                'type' => 'comment', 'content' => '<t>Privacy post</t>', 'created_at' => '2026-01-01 00:00:00',
            ]],
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

    #[DataProvider('categoryActors')]
    public function test_category_includes_and_counts_match_visibility(bool $preloaded, ?int $actor): void
    {
        $this->prepareDatabase(['group_permission' => [
            ['group_id' => 2, 'permission' => 'badges.viewList'],
            ['group_id' => 3, 'permission' => 'badges.viewList'],
        ]]);
        $this->app();
        $observed = [];
        $preloadedBadges = [];
        BadgeCategory::retrieved(function (BadgeCategory $category) use ($preloaded, &$observed, &$preloadedBadges) {
            if ($preloaded) {
                // Repeated category instances must not make this fixture itself
                // introduce N+1 reads while simulating a preloaded relationship.
                $preloadedBadges[$category->id] ??= $category->badges()->get();
                $category->setRelation('badges', $preloadedBadges[$category->id]);
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
            'owner without permission' => [2, false, ['100', '101'], 1, true],
            'stranger without permission' => [3, false, [], 0, false],
            'stranger with permission' => [3, true, ['101'], 1, true],
            'guest with permission' => [null, true, ['101'], 1, true],
            'administrator' => [1, false, ['100', '101'], 1, true],
        ];
    }

    #[DataProvider('privacyActors')]
    public function test_final_user_badge_and_profile_responses_respect_privacy(?int $actor, bool $permission, array $ids, int $count, bool $decoration): void
    {
        // Installation grants this permission to guests (and thus members).
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
        $this->assertSame($count, $profile['badgeCount']);
        // A hidden primary falls back to an eligible visible decoration.
        $this->assertSame($decoration ? 'Secret' : null, $profile['primaryBadgeName']);
        $this->assertSame($decoration ? 'fas fa-award' : null, $profile['primaryBadgeIcon']);
        $this->assertSame($decoration ? [['name' => 'Secret', 'icon' => 'fas fa-award']] : null, $profile['visibleBadges']);
    }

    public static function earnedBadgeIncludes(): array
    {
        $cases = [];
        foreach ([null, 3, 2, 1] as $actor) {
            foreach ([null, 'badge', 'badge.category'] as $include) {
                $cases[($actor ?? 'guest').'/'.($include ?? 'defaults')] = [$actor, $include];
            }
        }

        return $cases;
    }

    #[DataProvider('earnedBadgeIncludes')]
    public function test_earned_catalog_hidden_and_inactive_badges_keep_relationships_without_bypassing_privacy(?int $actor, ?string $include): void
    {
        $db = $this->database();
        $db->table('fof_badges')->whereIn('id', [101, 102])->update([
            'category_id' => 101,
            'actions' => json_encode(['send_notification' => true]),
            'trigger_config' => json_encode(['PRIVATE CONFIGURATION' => true]),
        ]);
        $db->table('fof_badge_user')->insert([
            'id' => 102, 'user_id' => 2, 'badge_id' => 102,
            'show_on_card' => true, 'earned_at' => '2026-01-03 00:00:00',
        ]);
        $query = ['filter' => ['user' => 2]];
        if ($include !== null) {
            $query['include'] = $include;
        }
        $body = $this->get('/api/user-badges', $actor, $query);
        $expected = in_array($actor, [1, 2], true) ? ['100', '101', '102'] : ['101', '102'];
        $this->assertEqualsCanonicalizing($expected, array_column($body['data'], 'id'));
        $badges = array_column(array_filter($body['included'] ?? [], fn ($item) => $item['type'] === 'badges'), null, 'id');
        $this->assertEqualsCanonicalizing($expected, array_keys($badges));
        foreach ($body['data'] as $award) {
            $this->assertSame(['type' => 'badges', 'id' => $award['id']], $award['relationships']['badge']['data']);
        }
        foreach ([101, 102] as $id) {
            if ($actor !== 1) {
                $this->assertArrayNotHasKey('actions', $badges[$id]['attributes']);
                $this->assertArrayNotHasKey('triggerConfig', $badges[$id]['attributes']);
            }
            if ($include === 'badge.category') {
                $this->assertSame($actor === 1 ? ['type' => 'badge-categories', 'id' => '101'] : null, $badges[$id]['relationships']['category']['data']);
            }
        }
        if ($actor !== 1) {
            $this->assertStringNotContainsString('PRIVATE CONFIGURATION', json_encode($body));
            $categories = array_filter($body['included'] ?? [], fn ($item) => $item['type'] === 'badge-categories');
            $this->assertNotContains('101', array_column($categories, 'id'));
        }
        if (! in_array($actor, [1, 2], true)) {
            $this->assertStringNotContainsString('PRIVATE REASON', json_encode($body));
        }
    }

    public static function nestedSurfaces(): array
    {
        return [
            'post show' => ['/api/posts/100', 'user'],
            'post index' => ['/api/posts', 'user'],
            'discussion show' => ['/api/discussions/100', 'user,lastPostedUser,firstPost.user,lastPost.user'],
            'discussion index' => ['/api/discussions', 'user,lastPostedUser'],
        ];
    }

    public function test_catalog_does_not_include_disabled_category_or_private_configuration(): void
    {
        $body = $this->get('/api/badges', 3, ['include' => 'category']);
        $this->assertEqualsCanonicalizing(['100', '103'], array_column($body['data'], 'id'));
        $badges = array_column($body['data'], null, 'id');
        $this->assertNull($badges[103]['relationships']['category']['data']);
        $this->assertEqualsCanonicalizing(['100'], array_column($body['included'], 'id'));
        foreach ($badges as $badge) {
            $this->assertArrayNotHasKey('actions', $badge['attributes']);
            $this->assertArrayNotHasKey('triggerConfig', $badge['attributes']);
        }
    }

    public static function managers(): array
    {
        return [['badges.moderate'], ['badges.giveManually']];
    }

    #[DataProvider('managers')]
    public function test_managers_can_inspect_hidden_awards_without_public_view_permission(string $permission): void
    {
        $this->database()->table('group_permission')->where('permission', 'badges.viewUserBadges')->delete();
        $this->database()->table('group_permission')->insert(['group_id' => 3, 'permission' => $permission]);
        $body = $this->get('/api/user-badges', 3, ['filter' => ['user' => 2]]);
        $this->assertEqualsCanonicalizing(['100', '101'], array_column($body['data'], 'id'));
        $awards = array_column($body['data'], null, 'id');
        $this->assertSame('PRIVATE REASON', $awards[100]['attributes']['reason']);
        $profile = $this->get('/api/users/2', 3)['data']['attributes'];
        $this->assertSame(1, $profile['badgeCount']);
        $this->assertSame('Secret', $profile['primaryBadgeName']);
    }

    #[DataProvider('nestedSurfaces')]
    public function test_nested_final_responses_do_not_leak_decorations_without_permission(string $url, string $include): void
    {
        $this->database()->table('group_permission')->where('permission', 'badges.viewUserBadges')->delete();
        $body = $this->get($url, 3, ['include' => $include]);
        $users = array_values(array_filter($body['included'] ?? [], fn ($item) => $item['type'] === 'users' && $item['id'] === '2'));
        $this->assertCount(1, $users);
        $this->assertSame(0, $users[0]['attributes']['badgeCount']);
        $this->assertNull($users[0]['attributes']['primaryBadgeName']);
        $this->assertNull($users[0]['attributes']['primaryBadgeIcon']);
        $this->assertNull($users[0]['attributes']['visibleBadges']);
        $this->assertStringNotContainsString('PRIVATE REASON', json_encode($body));
        $this->assertStringNotContainsString('Secret', json_encode($body));
    }

    private function get(string $url, ?int $actor, array $query = []): array
    {
        $response = $this->send($this->request('GET', $url, $actor === null ? [] : ['authenticatedAs' => $actor])->withQueryParams($query));
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $response->getStatusCode(), json_encode($body));

        return $body;
    }
}
