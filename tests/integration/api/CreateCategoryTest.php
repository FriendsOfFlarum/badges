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

class CreateCategoryTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => 1, 'permission' => 'badges.moderate'],
            ],
        ]);
    }

    /** @test */
    public function guest_cannot_create_category(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badge-categories', [
                'json' => [
                    'data' => [
                        'type' => 'badge-categories',
                        'attributes' => [
                            'name' => 'New Category',
                        ],
                    ],
                ],
            ])
        );

        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    /** @test */
    public function normal_user_cannot_create_category(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badge-categories', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'type' => 'badge-categories',
                        'attributes' => [
                            'name' => 'New Category',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function admin_can_create_category(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badge-categories', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badge-categories',
                        'attributes' => [
                            'name' => 'New Category',
                            'description' => 'A new badge category',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('New Category', $body['data']['attributes']['name']);
        $this->assertEquals('new-category', $body['data']['attributes']['slug']);
    }

    /** @test */
    public function category_requires_name(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badge-categories', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badge-categories',
                        'attributes' => [
                            'description' => 'A category without a name',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function slug_auto_generated_from_name(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badge-categories', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badge-categories',
                        'attributes' => [
                            'name' => 'My Special Category',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('my-special-category', $body['data']['attributes']['slug']);
    }
}
