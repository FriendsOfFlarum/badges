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

class DeleteCategoryTest extends TestCase
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
    public function guest_cannot_delete_category(): void
    {
        $response = $this->send(
            $this->request('DELETE', '/api/badge-categories/100')
        );

        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    /** @test */
    public function admin_can_delete_category(): void
    {
        $response = $this->send(
            $this->request('DELETE', '/api/badge-categories/100', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());
    }
}
