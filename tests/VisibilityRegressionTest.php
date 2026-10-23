<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

require __DIR__.'/bootstrap.php';

use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\BadgeVisibility;
use FoF\Badges\UserBadge;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;

$db = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
$resolver = new ConnectionResolver(['test' => $db]);
$resolver->setDefaultConnection('test');
Model::setConnectionResolver($resolver);
$db->statement('CREATE TABLE fof_badge_user (id INTEGER, user_id INTEGER, badge_id INTEGER, show_on_card INTEGER)');
$db->statement('CREATE TABLE fof_badges (id INTEGER, is_active INTEGER, is_visible INTEGER)');
foreach ([[1, 1, 1], [2, 1, 0], [3, 2, 1], [4, 2, 0]] as [$id, $owner, $shown]) {
    $db->table('fof_badge_user')->insert(['id' => $id, 'user_id' => $owner, 'badge_id' => 1, 'show_on_card' => $shown]);
}
foreach ([[1, 1, 1], [2, 1, 0], [3, 0, 1]] as [$id, $active, $visible]) {
    $db->table('fof_badges')->insert(['id' => $id, 'is_active' => $active, 'is_visible' => $visible]);
}
$actor = new class extends User {
    public $permissions = [];

    public function hasPermission($permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    public function can($ability, $arguments = []): bool
    {
        return $ability === 'viewAny' && $this->hasPermission($arguments === UserBadge::class ? 'badges.viewUserBadges' : 'badges.viewList');
    }
};
$checks = 0;
$check = function ($expected, $actual, $label) use (&$checks) {
    if ($expected !== $actual) {
        throw new RuntimeException($label.': '.var_export($actual, true));
    }
    $checks++;
};
foreach ([[null, [], []], [null, ['badges.viewUserBadges'], [1, 3]], [1, [], [1, 2]], [1, ['badges.viewList'], [1, 2]], [1, ['badges.viewUserBadges'], [1, 2, 3]], [1, ['badges.moderate'], [1, 2, 3, 4]], [1, ['badges.giveManually'], [1, 2, 3, 4]]] as [$id, $permissions, $expected]) {
    $actor->id = $id;
    $actor->permissions = $permissions;
    $query = UserBadge::query();
    BadgeVisibility::userBadges($query, $actor);
    $check($expected, $query->orderBy('id')->pluck('id')->all(), 'Scoped holder list');
    $badge = new UserBadge();
    $badge->user_id = 1;
    $badge->show_on_card = false;
    $badge->setRelation('badge', new Badge());
    $check(false, (bool) BadgeVisibility::decoration($actor, $badge), 'Hidden decoration even for owner/moderator');
}
$actor->id = 1;
$actor->permissions = ['badges.viewUserBadges'];
$earned = new UserBadge();
$earned->user_id = 2;
$earned->show_on_card = true;
$secret = new Badge();
$secret->is_visible = false;
$earned->setRelation('badge', $secret);
$check(true, BadgeVisibility::decoration($actor, $earned), 'Earned secret remains displayable');
$query = Badge::query();
BadgeVisibility::catalog($query, $actor);
$check([1], $query->pluck('id')->all(), 'Catalog hides secret and inactive badges');
$container = new \Illuminate\Container\Container();
\Illuminate\Container\Container::setInstance($container);
$container->instance('flarum.settings', new class {
    public function get($key, $default = null)
    {
        return $default;
    }
});
$serializer = (new ReflectionClass(\Flarum\Api\Serializer\UserSerializer::class))->newInstanceWithoutConstructor();
$serializer->setRequest(\Flarum\Http\RequestUtil::withActor(new \Laminas\Diactoros\ServerRequest(), $actor));
$secret->name = 'Earned secret';
$secret->icon = 'fas fa-star';
$hidden = clone $earned;
$hidden->show_on_card = false;
$hidden->is_primary = true;
$user = new User();
$user->setRelation('userBadges', new \Illuminate\Database\Eloquent\Collection([$hidden, $earned]));
foreach (require dirname(__DIR__).'/extend.php' as $extender) {
    if (! $extender instanceof \Flarum\Extend\ApiSerializer) {
        continue;
    }
    $reflection = new ReflectionObject($extender);
    if ($reflection->getProperty('serializerClass')->getValue($extender) !== \Flarum\Api\Serializer\UserSerializer::class) {
        continue;
    }
    foreach ($reflection->getProperty('attributes')->getValue($extender) as $callback) {
        $attributes = $callback($serializer, $user);
        $check(1, $attributes['badgeCount'], 'Serialized count excludes hidden badges');
        $check('Earned secret', $attributes['primaryBadgeName'], 'Hidden primary falls back');
        $check([['name' => 'Earned secret', 'icon' => 'fas fa-star']], $attributes['visibleBadges'], 'Earned hidden catalog badge displayed');
        $actor->permissions = [];
        $check(['badgeCount' => 0, 'primaryBadgeName' => null, 'primaryBadgeIcon' => null, 'visibleBadges' => null], $callback($serializer, $user), 'Preloaded attributes respect actor permission');
    }
}
echo "Visibility regression checks passed: {$checks}\n";
