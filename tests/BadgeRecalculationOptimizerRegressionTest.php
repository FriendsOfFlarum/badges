<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

// Uses only an explicitly created SQLite :memory: database; never boots Flarum.
require __DIR__.'/bootstrap.php';

use FoF\Badges\Badge;
use FoF\Badges\Service\BadgeRecalculationOptimizer;
use FoF\Badges\Trigger\MetricManager;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;

$connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
$resolver = new ConnectionResolver(['test' => $connection]);
$resolver->setDefaultConnection('test');
Model::setConnectionResolver($resolver);
$connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, comment_count INTEGER)');
$connection->statement('CREATE TABLE fof_badge_user (user_id INTEGER, badge_id INTEGER)');
$connection->table('users')->insert([
    ['id' => 1, 'comment_count' => 0],
    ['id' => 2, 'comment_count' => 20],
    ['id' => 3, 'comment_count' => 0],
    ['id' => 4, 'comment_count' => 0],
]);
$connection->table('fof_badge_user')->insert([
    ['user_id' => 1, 'badge_id' => 10],
    ['user_id' => 1, 'badge_id' => 11],
    ['user_id' => 4, 'badge_id' => 11],
]);
$manager = new class extends MetricManager {
    public function __construct()
    {
    }

    public function isAvailable(string $type): bool
    {
        return true;
    }
};
$optimizer = new BadgeRecalculationOptimizer($manager);
$badge = new Badge();
$badge->id = 10;
$badge->trigger_config = ['conditions' => [['metric' => 'post_count', 'operator' => '>=', 'value' => 10]]];
$other = clone $badge;
$other->id = 11;
$other->trigger_config = ['conditions' => [['metric' => 'post_count', 'operator' => '>=', 'value' => 15]]];
$checks = 0;
$check = function ($expected, $actual, string $message) use (&$checks) {
    if ($expected !== $actual) {
        throw new RuntimeException($message.': '.var_export($actual, true));
    }
    $checks++;
};
$ids = fn ($query) => $query->orderBy('id')->pluck('id')->all();
$check([], $optimizer->getUserIdsToSkip($badge, false), 'Revocable holders cannot be skipped');
$check([1], $optimizer->getUserIdsToSkip($badge, true), 'noRevoke skips holders');
$check([], $optimizer->getUserIdsToSkip($badge, true, true), 'Reapply overrides skips');
$check(false, $optimizer->isMonotonicSafe($badge), 'Post counts can decrease');
$check([1, 2], $ids($optimizer->buildOptimizedUserQuery($badge)), 'Single includes failing holder and qualifying nonholder');
$check([2], $ids($optimizer->buildOptimizedUserQuery($badge, [1])), 'Holder union cannot bypass exclusion');
$check([1], $ids($optimizer->buildOptimizedUserQuery($badge, [], true)), 'Manual mode includes only holders');
$check([], $ids($optimizer->buildOptimizedUserQuery($badge, [1], true)), 'Manual exclusions');
foreach ([[false, false, [1, 2, 4]], [true, false, [2, 4]], [true, true, [1, 2, 4]]] as [$noRevoke, $reapply, $expected]) {
    $groups = $optimizer->groupBadgesByOptimizationStrategy(collect([$badge, $other]), $noRevoke, $reapply);
    $actual = $groups[0]['userIds'];
    sort($actual);
    $check($expected, $actual, 'Grouped holder union / skip intersection / reapply');
}
$badge->trigger_config = ['conditions' => [['metric' => 'likes_given', 'operator' => '>=', 'value' => 10]]];
$check([1, 2, 3, 4], $ids($optimizer->buildOptimizedUserQuery($badge)), 'Unfilterable candidates remain unrestricted');
$connection->statement('ALTER TABLE users ADD COLUMN username TEXT');
$connection->statement('ALTER TABLE users ADD COLUMN display_name TEXT');
$connection->table('users')->where('id', 1)->update(['username' => 'literal%_!\\name']);
$connection->table('users')->where('id', 4)->update(['username' => 'literalXYZname']);
foreach (['%', '_', '!', '\\'] as $needle) {
    $state = new \Flarum\Search\Database\DatabaseSearchState(new \Flarum\User\User(), false);
    $state->setQuery(\FoF\Badges\UserBadge::query());
    (new \FoF\Badges\Search\Filter\SearchFilter())->filter($state, $needle, false);
    $check([1], $state->getQuery()->distinct()->pluck('user_id')->all(), 'LIKE literal '.$needle);
}
echo "Optimizer/search regression checks passed: {$checks}\n";
