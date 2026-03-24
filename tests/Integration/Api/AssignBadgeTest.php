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
use PHPUnit\Framework\Attributes\Test;
use Flarum\User\User;

class AssignBadgeTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        $this->prepareDatabase([
            'fof_badges' => [
                ['id' => 100, 'name' => 'Test Badge', 'slug' => 'test-badge', 'description' => 'A test badge', 'icon' => 'fas fa-star', 'icon_color' => '#ffffff', 'background_color' => '#667eea', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 0, 'trigger_config' => null, 'actions' => null],
            ],
            User::class => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => 1, 'permission' => 'badges.moderate'],
                ['group_id' => 1, 'permission' => 'badges.giveManually'],
            ],
        ]);
    }

    #[Test]
    public function guest_cannot_assign_badge(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/user-badges', [
                'json' => [
                    'data' => [
                        'type' => 'user-badges',
                        'attributes' => [
                            'userId' => 2,
                            'badgeId' => 100,
                            'reason' => 'Test reason',
                        ],
                    ],
                ],
            ])
        );

        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    #[Test]
    public function normal_user_cannot_assign_badge(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/user-badges', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'type' => 'user-badges',
                        'attributes' => [
                            'userId' => 2,
                            'badgeId' => 100,
                            'reason' => 'Test reason',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_assign_badge_to_user(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/user-badges', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'user-badges',
                        'attributes' => [
                            'userId' => 2,
                            'badgeId' => 100,
                            'reason' => 'Great contribution!',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('Great contribution!', $body['data']['attributes']['reason']);
        $this->assertEquals('manual', $body['data']['attributes']['grantedBy']);
    }

    #[Test]
    public function cannot_assign_same_badge_twice(): void
    {
        // First assignment
        $this->send(
            $this->request('POST', '/api/user-badges', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'user-badges',
                        'attributes' => [
                            'userId' => 2,
                            'badgeId' => 100,
                        ],
                    ],
                ],
            ])
        );

        // Second assignment
        $response = $this->send(
            $this->request('POST', '/api/user-badges', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'user-badges',
                        'attributes' => [
                            'userId' => 2,
                            'badgeId' => 100,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_revoke_badge(): void
    {
        // First assign the badge
        $assignResponse = $this->send(
            $this->request('POST', '/api/user-badges', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'user-badges',
                        'attributes' => [
                            'userId' => 2,
                            'badgeId' => 100,
                        ],
                    ],
                ],
            ])
        );

        $assignBody = json_decode($assignResponse->getBody()->getContents(), true);
        $userBadgeId = $assignBody['data']['id'];

        // Then revoke it
        $response = $this->send(
            $this->request('DELETE', "/api/user-badges/{$userBadgeId}", [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());
    }
}
