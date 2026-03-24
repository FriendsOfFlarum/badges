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

class CreateBadgeTest extends TestCase
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
            User::class => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => 1, 'permission' => 'badges.moderate'],
            ],
        ]);
    }

    #[Test]
    public function guest_cannot_create_badge(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badges', [
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'attributes' => [
                            'name' => 'Test Badge',
                            'description' => 'A test badge',
                            'icon' => 'fas fa-star',
                            'iconColor' => '#ffffff',
                            'backgroundColor' => '#667eea',
                        ],
                    ],
                ],
            ])
        );

        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    #[Test]
    public function normal_user_cannot_create_badge(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badges', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'attributes' => [
                            'name' => 'Test Badge',
                            'description' => 'A test badge',
                            'icon' => 'fas fa-star',
                            'iconColor' => '#ffffff',
                            'backgroundColor' => '#667eea',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_create_badge(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badges', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'attributes' => [
                            'name' => 'Test Badge',
                            'description' => 'A test badge',
                            'icon' => 'fas fa-star',
                            'iconColor' => '#ffffff',
                            'backgroundColor' => '#667eea',
                            'isActive' => true,
                            'isVisible' => true,
                            'categoryId' => 100,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('Test Badge', $body['data']['attributes']['name']);
        $this->assertEquals('test-badge', $body['data']['attributes']['slug']);
        $this->assertEquals('fas fa-star', $body['data']['attributes']['icon']);
    }

    #[Test]
    public function badge_requires_name(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badges', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'attributes' => [
                            'description' => 'A test badge',
                            'icon' => 'fas fa-star',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_create_badge_with_trigger_config(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/badges', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'badges',
                        'attributes' => [
                            'name' => 'Auto Badge',
                            'description' => 'An automatic badge',
                            'icon' => 'fas fa-trophy',
                            'iconColor' => '#ffffff',
                            'backgroundColor' => '#f1c40f',
                            'triggerConfig' => [
                                'conditions' => [
                                    ['metric' => 'post_count', 'operator' => '>=', 'value' => 50],
                                ],
                                'logic' => 'AND',
                            ],
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertNotNull($body['data']['attributes']['triggerConfig']);
        $this->assertCount(1, $body['data']['attributes']['triggerConfig']['conditions']);
    }
}