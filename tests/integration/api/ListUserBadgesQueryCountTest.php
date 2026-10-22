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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ListUserBadgesQueryCountTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-badges');
        $users = $awards = [];
        for ($i = 1; $i <= 8; $i++) {
            $users[] = ['id' => $i + 2, 'username' => "holder$i", 'email' => "holder$i@machine.local", 'is_email_confirmed' => 1];
            foreach ([100, 102, ...($i % 2 ? [101] : [])] as $badge) {
                $awards[] = ['id' => $i * 10 + $badge - 100, 'user_id' => $i + 2, 'badge_id' => $badge,
                    'granted_by' => 'manual', 'granted_by_user_id' => ($i % 8) + 3,
                    'show_on_card' => $badge !== 102, 'is_primary' => $badge === 102 && $i % 2 === 1,
                    'earned_at' => '2026-01-01 00:00:00'];
            }
        }
        $badges = [];
        foreach ([100 => ['Starter', 'fas fa-star', 8], 101 => ['Veteran', 'fas fa-trophy', 4], 102 => ['Hidden', 'fas fa-lock', 0]] as $id => [$name, $icon, $count]) {
            $badges[] = ['id' => $id, 'name' => $name, 'slug' => strtolower($name), 'description' => $name,
                'icon' => $icon, 'icon_color' => '#ffffff', 'background_color' => '#667eea',
                'is_active' => true, 'is_visible' => true, 'earned_count' => $count, 'order' => 0];
        }
        $this->prepareDatabase([
            User::class => array_merge([$this->normalUser()], $users),
            'fof_badges' => $badges,
            'fof_badge_user' => $awards,
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'badges.viewUserBadges'],
                ['group_id' => 3, 'permission' => 'badges.viewList'],
            ],
        ]);
    }

    public static function includesAndPages(): array
    {
        $cases = [];
        foreach (['user', 'grantedByUser', 'badge'] as $include) {
            foreach ([2, 8] as $size) {
                $cases["$include/$size"] = [$include, $size];
            }
        }
        return $cases;
    }

    #[Test]
    #[DataProvider('includesAndPages')]
    public function decoration_queries_are_fixed_for_each_include_and_page_size(string $include, int $size): void
    {
        $this->app();
        $db = $this->database();
        $db->enableQueryLog();
        $db->flushQueryLog();
        $response = $this->send($this->request('GET', '/api/user-badges', ['authenticatedAs' => 2])
            ->withQueryParams(['filter' => ['badge' => 100], 'include' => $include, 'page' => ['limit' => $size]]));
        $queries = array_column($db->getQueryLog(), 'query');
        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertCount($size, $body['data']);

        // wrapTable uses the actual connection prefix and driver quoting. Do not
        // strip quotes or assume an unprefixed table: that can silently match zero.
        $grammar = $db->getQueryGrammar();
        $table = preg_quote($grammar->wrapTable('fof_badge_user'), '/');
        $column = preg_quote($grammar->wrap('user_id'), '/');
        $decorations = array_filter($queries, fn ($sql) => preg_match('/^select \* from '.$table.' where (?:'.$table.'\.)?'.$column.' (?:=\s|in\b)/i', $sql));
        $allAwardReads = array_filter($queries, fn ($sql) => preg_match('/^select .* from '.$table.'(?:\s|$)/i', $sql));
        $this->assertCount($include === 'badge' ? 0 : 1, $decorations, implode("\n", $queries));
        // Positive control even in badge-only mode; a broken matcher cannot pass.
        // Badge serialization reads the actor's earned IDs once; user
        // serialization instead adds the single decoration batch read.
        $this->assertCount(3, $allAwardReads, implode("\n", $queries));

        $included = $body['included'] ?? [];
        $this->assertCount($include === 'badge' ? 1 : $size, $included);
        foreach ($included as $resource) {
            $this->assertSame($include === 'badge' ? 'badges' : 'users', $resource['type']);
            if ($include !== 'badge') {
                $this->assertDecoration($resource['attributes'], (int) substr($resource['attributes']['username'], 6));
            }
        }
    }

    private function assertDecoration(array $attributes, int $holder, bool $denied = false): void
    {
        $expected = $denied ? [] : ($holder % 2
            ? [['name' => 'Veteran', 'icon' => 'fas fa-trophy'], ['name' => 'Starter', 'icon' => 'fas fa-star']]
            : [['name' => 'Starter', 'icon' => 'fas fa-star']]);
        foreach (['badgeCount', 'primaryBadgeName', 'primaryBadgeIcon', 'visibleBadges'] as $key) {
            $this->assertArrayHasKey($key, $attributes);
        }
        $this->assertSame(count($expected), $attributes['badgeCount']);
        $this->assertSame($expected[0]['name'] ?? null, $attributes['primaryBadgeName']);
        $this->assertSame($expected[0]['icon'] ?? null, $attributes['primaryBadgeIcon']);
        $this->assertSame($expected ?: null, $attributes['visibleBadges']);
    }

    public static function deniedActors(): array
    {
        return ['guest' => [null], 'other member' => [2], 'owner' => [3]];
    }

    #[Test]
    #[DataProvider('deniedActors')]
    public function denied_view_permission_hides_decorations_except_from_owner(?int $actor): void
    {
        $this->app();
        $this->database()->table('group_permission')->where('permission', 'badges.viewUserBadges')->delete();
        $response = $this->send($this->request('GET', '/api/users/3', $actor ? ['authenticatedAs' => $actor] : []));
        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertDecoration($body['data']['attributes'], 1, $actor !== 3);

        if ($actor === 3) {
            $response = $this->send($this->request('GET', '/api/user-badges', ['authenticatedAs' => 3])
                ->withQueryParams(['filter' => ['user' => 3], 'include' => 'badge']));
            $this->assertSame(200, $response->getStatusCode());
            $body = json_decode((string) $response->getBody(), true);
            $this->assertCount(3, $body['data'], 'Owner management retains the hidden primary award');
            $hidden = array_values(array_filter($body['data'], fn ($award) => ! $award['attributes']['showOnCard']));
            $this->assertCount(1, $hidden);
            $this->assertTrue($hidden[0]['attributes']['isPrimary']);
        }
    }
}
