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

class ListBadgesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $this->prepareDatabase([
            'fof_badge_cat' => [
                ['id' => 100, 'name' => 'Test Achievement', 'slug' => 'test-achievement', 'description' => 'Achievement badges', 'is_enabled' => true, 'order' => 0],
            ],
            'fof_badges' => [
                ['id' => 100, 'name' => 'Test First Post', 'slug' => 'test-first-post', 'description' => 'Created your first post', 'icon' => 'fas fa-pen', 'icon_color' => '#ffffff', 'background_color' => '#667eea', 'category_id' => 100, 'is_active' => true, 'is_visible' => true, 'order' => 0, 'trigger_config' => null, 'actions' => null],
                ['id' => 101, 'name' => 'Test 100 Posts', 'slug' => 'test-100-posts', 'description' => 'Created 100 posts', 'icon' => 'fas fa-fire', 'icon_color' => '#ffffff', 'background_color' => '#e74c3c', 'category_id' => 100, 'is_active' => true, 'is_visible' => true, 'order' => 1, 'trigger_config' => '{"conditions":[{"metric":"post_count","operator":">=","value":100}],"logic":"AND"}', 'actions' => null],
                ['id' => 102, 'name' => 'Hidden Badge', 'slug' => 'test-hidden', 'description' => 'A hidden badge', 'icon' => 'fas fa-eye-slash', 'icon_color' => '#ffffff', 'background_color' => '#333333', 'category_id' => null, 'is_active' => true, 'is_visible' => false, 'order' => 2, 'trigger_config' => null, 'actions' => null],
            ],
            User::class => [
                $this->normalUser(),
            ],
        ]);
    }

    #[Test]
    public function guest_can_list_visible_badges(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badges')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $slugs = array_column(array_column($body['data'], 'attributes'), 'slug');

        $this->assertContains('test-first-post', $slugs);
        $this->assertContains('test-100-posts', $slugs);
        $this->assertNotContains('test-hidden', $slugs);
    }

    #[Test]
    public function admin_can_list_all_badges_including_hidden(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badges', [
                'authenticatedAs' => 1,
            ])->withQueryParams([
                'filter' => ['includeHidden' => true],
            ])
        );

        fwrite(STDERR, "LIST RESPONSE: " . $response->getStatusCode() . " " . $response->getBody()->getContents() . "\n");
        $response->getBody()->rewind();

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $slugs = array_column(array_column($body['data'], 'attributes'), 'slug');

        $this->assertContains('test-hidden', $slugs);
        $this->assertGreaterThanOrEqual(3, count($body['data']));
    }

    #[Test]
    public function badges_include_category_relationship(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badges')->withQueryParams([
                'include' => 'category',
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $included = collect($body['included'] ?? []);
        $categories = $included->where('type', 'badge-categories');
        $achievementCategory = $categories->firstWhere('attributes.name', 'Test Achievement');

        $this->assertNotNull($achievementCategory, 'A category with name "Test Achievement" should exist in included resources');
    }
}