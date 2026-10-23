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
    public ?array $permissions = [];

    public function isAdmin(): bool
    {
        return false;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    public function can(string $ability, mixed $arguments = null): bool
    {
        if ($ability === 'viewAny') {
            return $this->hasPermission($arguments === UserBadge::class ? 'badges.viewUserBadges' : 'badges.viewList');
        }
        if ($ability === 'create') {
            return $this->hasPermission($arguments === UserBadge::class ? 'badges.giveManually' : 'badges.moderate');
        }

        return false;
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
    $searcher = (new ReflectionClass(\FoF\Badges\Search\UserBadgeSearcher::class))->newInstanceWithoutConstructor();
    $check($expected, $searcher->getQuery($actor)->orderBy('id')->pluck('id')->all(), 'Search must enforce same privacy');
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
$user = new User();
$hidden = clone $earned;
$hidden->show_on_card = false;
$hidden->is_primary = true;
$user->setRelation('userBadges', new \Illuminate\Database\Eloquent\Collection([$hidden, $earned]));
$check($earned, \FoF\Badges\UserBadgeHelper::getPrimaryBadge($user, $actor), 'Hidden primary falls back to visible earned secret');
$check(1, \FoF\Badges\UserBadgeHelper::getUserBadges($user, $actor)->count(), 'Filtered count');
$actor->permissions = [];
$check(0, \FoF\Badges\UserBadgeHelper::getUserBadges($user, $actor)->count(), 'Loaded collection must not cache actor visibility');

$context = new \Flarum\Api\Context(new \Tobyz\JsonApiServer\JsonApi(), \Flarum\Http\RequestUtil::withActor(new \Laminas\Diactoros\ServerRequest(), $actor));
foreach ([\FoF\Badges\Api\Resource\BadgeResource::class, \FoF\Badges\Api\Resource\BadgeCategoryResource::class, \FoF\Badges\Api\Resource\UserBadgeResource::class] as $class) {
    $resource = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    foreach ($resource->endpoints() as $endpoint) {
        if ($endpoint instanceof \Flarum\Api\Endpoint\Create) {
            $actor->permissions = ['badges.moderate', 'badges.giveManually'];
            $check(true, $endpoint->isVisible($context), 'Model-less create uses class policy');
            $actor->permissions = [];
            $check(false, $endpoint->isVisible($context), 'Unauthorized create rejected');
        }
    }
}

// Execute the actual extender callback and inspect core's compiled eager loads.
$category = new \FoF\Badges\BadgeCategory();
$public = new Badge();
$public->is_visible = true;
$public->is_active = true;
$category->setRelation('badges', new \Illuminate\Database\Eloquent\Collection([$public, $secret]));
$resource = (new ReflectionClass(\FoF\Badges\Api\Resource\BadgeCategoryResource::class))->newInstanceWithoutConstructor();
foreach ($resource->fields() as $field) {
    if ($field->name !== 'badges') {
        continue;
    }
    $actor->permissions = ['badges.viewList'];
    $check([$public], $field->getValue($context->withModel($category)->withInclude([])), 'Preloaded category include filters secret badges');
    $actor->permissions = [];
    $check([], $field->getValue($context->withModel($category)->withInclude([])), 'Include requires catalog policy');
}
$resource = (new ReflectionClass(\FoF\Badges\Api\Resource\BadgeResource::class))->newInstanceWithoutConstructor();
foreach ($resource->fields() as $field) {
    if ($field->name !== 'actions') {
        continue;
    }

    try {
        $field->setValue(new Badge(), ['add_to_group' => true], $context);

        throw new RuntimeException('Actions setter accepted boolean group');
    } catch (\Flarum\Foundation\ValidationException $exception) {
        $checks++;
    }
}
$actor->permissions = ['badges.moderate'];
$badge = new Badge();
$badge->actions = ['add_to_group' => 4];

try {
    $resource->saving($badge, $context);

    throw new RuntimeException('Saving allowed moderator to configure group action');
} catch (\Flarum\User\Exception\PermissionDeniedException $exception) {
    $checks++;
}

$extenders = require dirname(__DIR__).'/extend.php';
foreach ($extenders as $extender) {
    if (! $extender instanceof \Flarum\Extend\ApiResource) {
        continue;
    }
    $reflection = new ReflectionObject($extender);
    if ($reflection->getProperty('resourceClass')->getValue($extender) !== \Flarum\Api\Resource\DiscussionResource::class) {
        continue;
    }
    $callbacks = $reflection->getProperty('endpoint')->getValue($extender);
    $endpoint = \Flarum\Api\Endpoint\Show::make();
    foreach ($callbacks[\Flarum\Api\Endpoint\Show::class] as $callback) {
        $endpoint = $callback($endpoint);
    }
    $compile = new ReflectionMethod($endpoint, 'compileSimpleEagerLoads');
    $check([], $compile->invoke($endpoint, $context, []), 'No full posts load on normal discussion show');
    $check(['user.userBadges.badge'], $compile->invoke($endpoint, $context, ['user']), 'Included author still eager loaded');
    $check(['posts.user.userBadges', 'posts.user.userBadges.badge'], $compile->invoke($endpoint, $context, ['posts']), 'Posts load only when actually included');
}
echo "Visibility regression checks passed: {$checks}\n";
