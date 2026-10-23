<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Tests\unit;

use Flarum\User\User;
use FoF\Badges\UserBadge;
use FoF\Badges\UserBadgeHelper;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WeakMap;
use WeakReference;

class UserBadgeBatchTest extends TestCase
{
    private Manager $db;

    /** @var \Illuminate\Database\ConnectionResolverInterface|null */
    private $previousResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousResolver = Model::getConnectionResolver();
        $this->db = new Manager();
        // Prefix deliberately differs from the API fixture, exercising real SQL.
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'batch_']);
        $this->db->bootEloquent();
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('fof_badges', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name');
            $table->string('icon');
            $table->integer('earned_count');
        });
        $schema->create('fof_badge_user', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->integer('badge_id');
            $table->boolean('show_on_card');
            $table->boolean('is_primary');
        });
        $this->db->getConnection()->table('fof_badges')->insert([
            ['id' => 1, 'name' => 'Public', 'icon' => 'fas fa-star', 'earned_count' => 10],
            ['id' => 2, 'name' => 'Rare', 'icon' => 'fas fa-trophy', 'earned_count' => 2],
            ['id' => 3, 'name' => 'Hidden', 'icon' => 'fas fa-lock', 'earned_count' => 0],
        ]);
        $this->db->getConnection()->table('fof_badge_user')->insert([
            ['id' => 1, 'user_id' => 10, 'badge_id' => 1, 'show_on_card' => true, 'is_primary' => false],
            ['id' => 2, 'user_id' => 10, 'badge_id' => 2, 'show_on_card' => true, 'is_primary' => false],
            ['id' => 3, 'user_id' => 10, 'badge_id' => 3, 'show_on_card' => false, 'is_primary' => true],
            ['id' => 4, 'user_id' => 20, 'badge_id' => 1, 'show_on_card' => true, 'is_primary' => true],
        ]);
        $this->db->getConnection()->enableQueryLog();
    }

    protected function tearDown(): void
    {
        $this->db->getConnection()->disconnect();
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        parent::tearDown();
    }

    private function user(int $id, bool $allowed = false): User
    {
        // Keep real Eloquent relations while stubbing only actor permissions.
        $user = new class extends User {
            public bool $allowed = false;

            public function can(string $ability, mixed $arguments = null): bool
            {
                return $this->allowed;
            }

            public function hasPermission(string $permission): bool
            {
                return false;
            }

            public function isGuest(): bool
            {
                return $this->id === 0;
            }
        };
        $user->id = $id;
        $user->allowed = $allowed;

        return $user;
    }

    private function assertQueries(int $awards, int $badges): void
    {
        $connection = $this->db->getConnection();
        $queries = array_column($connection->getQueryLog(), 'query');
        foreach (['fof_badge_user' => $awards, 'fof_badges' => $badges] as $table => $count) {
            $quoted = preg_quote($connection->getQueryGrammar()->wrapTable($table), '/');
            $this->assertCount($count, array_filter($queries, fn ($sql) => preg_match('/^select .* from '.$quoted.'(?:\s|$)/i', $sql)), implode("\n", $queries));
        }
        $this->assertCount($awards + $badges, $queries);
    }

    #[Test]
    public function simultaneously_live_requests_have_independent_batches(): void
    {
        $first = new ServerRequest();
        $second = new ServerRequest();
        $a = UserBadgeHelper::forRequest($first);
        $b = UserBadgeHelper::forRequest($second);
        $this->assertSame($a, UserBadgeHelper::forRequest($first));
        $this->assertNotSame($a, $b);
        $one = $this->user(10);
        $two = $this->user(20);
        $a->queue($one);
        $b->queue($two);
        $a->load();
        $this->assertTrue($one->relationLoaded('userBadges'));
        $this->assertFalse($two->relationLoaded('userBadges'));
        $this->assertQueries(1, 1);
        $b->load();
        $this->assertSame([4], $two->getRelation('userBadges')->modelKeys());
        $this->assertQueries(2, 2);
        $a->load();
        $b->load();
        $this->assertQueries(2, 2);
    }

    #[Test]
    public function abandoning_a_request_releases_its_unloaded_queue(): void
    {
        $request = new ServerRequest();
        $batch = UserBadgeHelper::forRequest($request);
        // PHPUnit retains mocks until teardown; use a real model for GC checks.
        $user = new User();
        $user->id = 10;
        $batch->queue($user);
        $requestRef = WeakReference::create($request);
        $batchRef = WeakReference::create($batch);
        $userRef = WeakReference::create($user);
        $maps = [];
        foreach ((new ReflectionClass(UserBadgeHelper::class))->getStaticProperties() as $property) {
            if ($property instanceof WeakMap) {
                $maps[] = $property;
            }
        }
        $this->assertCount(1, $maps, 'Request scopes must be held by a WeakMap');
        $this->assertSame($batch, $maps[0][$request]);
        unset($user, $batch, $request);
        gc_collect_cycles();
        $this->assertNull($requestRef->get());
        $this->assertNull($batchRef->get());
        $this->assertNull($userRef->get());
        $this->assertCount(0, $maps[0]);
        $this->assertQueries(0, 0);

        $next = new ServerRequest();
        $nextUser = $this->user(20);
        $nextBatch = UserBadgeHelper::forRequest($next);
        $nextBatch->queue($nextUser);
        $nextBatch->load();
        $this->assertQueries(1, 1);
        $this->assertSame([4], $nextUser->getRelation('userBadges')->modelKeys());
    }

    #[Test]
    public function repeated_actor_filtered_reads_preserve_the_raw_relation(): void
    {
        $request = new ServerRequest();
        $batch = UserBadgeHelper::forRequest($request);
        $owner = $this->user(10);
        $denied = $this->user(30);
        $guest = $this->user(0);
        $allowed = $this->user(40, true);
        foreach ([$denied, $owner, $guest, $allowed, $denied, $owner] as $actor) {
            $visible = $actor === $owner || $actor === $allowed;
            $this->assertSame($visible ? [1, 2] : [], UserBadgeHelper::getUserBadges($owner, $actor, $batch)->modelKeys());
            $this->assertSame($visible ? 2 : null, UserBadgeHelper::getPrimaryBadge($owner, $actor, $batch)?->id);
            $this->assertSame([1, 2, 3], $owner->getRelation('userBadges')->modelKeys());
        }
        $this->assertQueries(1, 1);
    }

    #[Test]
    public function partial_nested_preloads_are_completed_without_replacing_constrained_collections(): void
    {
        $owner = $this->user(10);
        $other = $this->user(20);
        $awards = UserBadge::whereIn('id', [1, 2])->get();
        $awards[0]->load('badge'); // First loaded, second missing: first-only checks fail.
        $loadedBadge = $awards[0]->getRelation('badge');
        $owner->setRelation('userBadges', $awards); // Deliberately excludes hidden award 3.
        $other->setRelation('userBadges', new Collection()); // Deliberately excludes award 4.
        $this->db->getConnection()->flushQueryLog();
        $request = new ServerRequest();
        $batch = UserBadgeHelper::forRequest($request);
        $batch->queue($owner);
        $batch->queue($owner);
        $batch->queue($other);
        $batch->load();
        $this->assertSame([1, 2], UserBadgeHelper::getUserBadges($owner, $owner, $batch)->modelKeys());
        $this->assertTrue($awards[1]->relationLoaded('badge'));
        $this->assertSame($loadedBadge, $awards[0]->getRelation('badge'));
        $this->assertSame($awards, $owner->getRelation('userBadges'));
        $this->assertSame([], UserBadgeHelper::getUserBadges($other, $other, $batch)->modelKeys());
        $this->assertSame(2, UserBadgeHelper::getPrimaryBadge($owner, $owner, $batch)?->id);
        $batch->load();
        UserBadgeHelper::getUserBadges($owner, $owner, $batch);
        $this->assertQueries(0, 1);
    }

    #[Test]
    public function duplicate_models_and_queue_entries_load_in_one_pair_of_queries(): void
    {
        $request = new ServerRequest();
        $batch = UserBadgeHelper::forRequest($request);
        $one = $this->user(10);
        $duplicate = $this->user(10);
        $two = $this->user(20);
        foreach ([$one, $duplicate, $one, $two] as $user) {
            $batch->queue($user);
        }
        $batch->load();
        foreach ([$one, $duplicate, $two] as $user) {
            $this->assertSame($user->id === 10 ? [1, 2] : [4], UserBadgeHelper::getUserBadges($user, $user, $batch)->modelKeys());
        }
        $this->assertQueries(1, 1);
    }
}
