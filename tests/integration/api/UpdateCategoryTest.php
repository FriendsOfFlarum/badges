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

class UpdateCategoryTest extends TestCase
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
            'users' => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => 1, 'permission' => 'badges.moderate'],
            ],
        ]);
    }

    /** @test */
    public function guest_cannot_update_category(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/badge-categories/100', [
                'json' => [
                    'data' => [
                        'type' => 'badge-categories',
                        'id' => '100',
                        'attributes' => [
                            'name' => 'Updated Category',
                        ],
                    ],
                ],
            ])
        );

        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    /** @test */
    public function admin_can_update_category_name(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/badge-categories/100', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badge-categories',
                        'id' => '100',
                        'attributes' => [
                            'name' => 'Updated Category Name',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('Updated Category Name', $body['data']['attributes']['name']);
    }

    /** @test */
    public function admin_can_disable_category(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/badge-categories/100', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badge-categories',
                        'id' => '100',
                        'attributes' => [
                            'isEnabled' => false,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertFalse($body['data']['attributes']['isEnabled']);
    }
}
