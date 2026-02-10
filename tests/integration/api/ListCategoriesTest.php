<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Tests\Integration\Api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class ListCategoriesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $this->prepareDatabase([
            'fof_badge_cat' => [
                ['id' => 100, 'name' => 'Test Achievement', 'slug' => 'test-achievement', 'description' => 'Achievement badges', 'is_enabled' => true, 'order' => 0],
                ['id' => 101, 'name' => 'Test Community', 'slug' => 'test-community', 'description' => 'Community badges', 'is_enabled' => true, 'order' => 1],
                ['id' => 102, 'name' => 'Disabled Category', 'slug' => 'test-disabled', 'description' => 'A disabled category', 'is_enabled' => false, 'order' => 2],
            ],
            'users' => [
                $this->normalUser(),
            ],
        ]);
    }

    /** @test */
    public function guest_can_list_enabled_categories(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badge-categories')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $slugs = array_column(array_column($body['data'], 'attributes'), 'slug');

        $this->assertContains('test-achievement', $slugs);
        $this->assertContains('test-community', $slugs);
        $this->assertNotContains('test-disabled', $slugs);
    }

    /** @test */
    public function admin_can_list_all_categories(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badge-categories', [
                'authenticatedAs' => 1,
            ])->withQueryParams([
                'filter' => ['includeDisabled' => true],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $slugs = array_column(array_column($body['data'], 'attributes'), 'slug');

        $this->assertContains('test-disabled', $slugs);
        $this->assertGreaterThanOrEqual(3, count($body['data']));
    }

    /** @test */
    public function categories_are_ordered_by_order(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/badge-categories')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $orders = array_column(array_column($body['data'], 'attributes'), 'order');

        // Verify that order values are in ascending (non-decreasing) order
        for ($i = 1; $i < count($orders); $i++) {
            $this->assertGreaterThanOrEqual($orders[$i - 1], $orders[$i], 'Categories should be ordered by their order field in ascending order');
        }
    }
}
