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

class UpdateBadgeTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $this->prepareDatabase([
            'fof_badge_cat' => [
                ['id' => 100, 'name' => 'Achievement', 'slug' => 'achievement', 'description' => null, 'is_enabled' => true, 'order' => 0],
            ],
            'fof_badges' => [
                ['id' => 100, 'name' => 'Test Badge', 'slug' => 'test-badge', 'description' => 'A test badge', 'icon' => 'fas fa-star', 'icon_color' => '#ffffff', 'background_color' => '#667eea', 'category_id' => 100, 'is_active' => true, 'is_visible' => true, 'order' => 0, 'trigger_config' => null, 'actions' => null],
            ],
            User::class => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => 1, 'permission' => 'badges.moderate'],
            ],
        ]);
    }

    #[Test]
    public function guest_cannot_update_badge(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/badges/100', [
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'id' => '100',
                        'attributes' => [
                            'name' => 'Updated Badge',
                        ],
                    ],
                ],
            ])
        );

        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    #[Test]
    public function normal_user_cannot_update_badge(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/badges/100', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'id' => '100',
                        'attributes' => [
                            'name' => 'Updated Badge',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_update_badge_name(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/badges/100', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'id' => '100',
                        'attributes' => [
                            'name' => 'Updated Badge Name',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('Updated Badge Name', $body['data']['attributes']['name']);
    }

    #[Test]
    public function admin_can_update_badge_visibility(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/badges/100', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'id' => '100',
                        'attributes' => [
                            'isVisible' => false,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertFalse($body['data']['attributes']['isVisible']);
    }

    #[Test]
    public function admin_can_update_badge_trigger_config(): void
    {
        $triggerConfig = [
            'conditions' => [
                ['metric' => 'post_count', 'operator' => 'gte', 'value' => 50],
            ],
            'logic' => 'and',
        ];

        $response = $this->send(
            $this->request('PATCH', '/api/badges/100', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'id' => '100',
                        'attributes' => [
                            'triggerConfig' => $triggerConfig,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertNotNull($body['data']['attributes']['triggerConfig']);
        $this->assertCount(1, $body['data']['attributes']['triggerConfig']['conditions']);
        $this->assertEquals('post_count', $body['data']['attributes']['triggerConfig']['conditions'][0]['metric']);
    }

    #[Test]
    public function update_with_invalid_name_fails(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/badges/100', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'id' => '100',
                        'attributes' => [
                            'name' => '',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }
}
