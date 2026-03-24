<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\BadgeValidator;
use FoF\Badges\UserBadge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends AbstractDatabaseResource<Badge>
 */
class BadgeResource extends AbstractDatabaseResource
{
    protected static ?int $cachedTotalUsers = null;
    protected static ?array $cachedEarnedIds = null;

    public function __construct(
        protected BadgeValidator $validator
    ) {
    }

    public function type(): string
    {
        return 'badges';
    }

    public function model(): string
    {
        return Badge::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $actor = $context->getActor();

        if (! $actor->hasPermission('badges.moderate')) {
            $query->where('is_active', true);
        }

        if ($actor->isGuest()) {
            $query->where('is_visible', true);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(1000, 1000)
                ->defaultInclude(['category'])
                ->defaultSort('order')
                ->eagerLoad(['category']),
            Endpoint\Show::make()
                ->defaultInclude(['category'])
                ->eagerLoad(['category']),
            Endpoint\Create::make()
                ->authenticated()
                ->can('create'),
            Endpoint\Update::make()
                ->authenticated()
                ->can('edit'),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(255),
            Schema\Str::make('slug')
                ->writable()
                ->maxLength(255),
            Schema\Str::make('description')
                ->writable()
                ->nullable(),
            Schema\Str::make('icon')
                ->writable()
                ->nullable(),
            Schema\Str::make('iconColor')
                ->property('icon_color')
                ->writable()
                ->nullable(),
            Schema\Str::make('backgroundColor')
                ->property('background_color')
                ->writable()
                ->nullable(),
            Schema\Boolean::make('isActive')
                ->property('is_active')
                ->writable(),
            Schema\Boolean::make('isVisible')
                ->property('is_visible')
                ->writable(),
            Schema\Integer::make('earnedCount')
                ->property('earned_count'),
            Schema\Integer::make('order')
                ->writable(),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\Integer::make('categoryId')
                ->property('category_id')
                ->writable()
                ->nullable(),
            Schema\Number::make('rarity')
                ->get(function (Badge $badge, Context $context) {
                    if (static::$cachedTotalUsers === null) {
                        static::$cachedTotalUsers = User::query()->count();
                    }

                    if (static::$cachedTotalUsers === 0) {
                        return 0.0;
                    }

                    return min(100.0, round(($badge->earned_count / static::$cachedTotalUsers) * 100, 1));
                }),
            Schema\Boolean::make('isEarned')
                ->get(function (Badge $badge, Context $context) {
                    $actor = $context->getActor();

                    if ($actor->isGuest()) {
                        return false;
                    }

                    if (static::$cachedEarnedIds === null) {
                        static::$cachedEarnedIds = UserBadge::where('user_id', $actor->id)
                            ->pluck('badge_id')
                            ->map(fn ($id) => (int) $id)
                            ->toArray();
                    }

                    return in_array((int) $badge->id, static::$cachedEarnedIds, true);
                }),
            Schema\Boolean::make('canEdit')
                ->get(fn (Badge $badge, Context $context) => $context->getActor()->hasPermission('badges.moderate')),
            Schema\Arr::make('triggerConfig')
                ->property('trigger_config')
                ->writable()
                ->nullable()
                ->visible(fn (Badge $badge, Context $context) => $context->getActor()->hasPermission('badges.moderate')),
            Schema\Arr::make('actions')
                ->writable()
                ->nullable()
                ->visible(fn (Badge $badge, Context $context) => $context->getActor()->hasPermission('badges.moderate')),
            Schema\Relationship\ToOne::make('category')
                ->type('badge-categories')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('order'),
            SortColumn::make('name'),
            SortColumn::make('earnedCount')
                ->column('earned_count'),
            SortColumn::make('createdAt')
                ->column('created_at'),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        if (empty($model->slug)) {
            $model->slug = Str::slug($model->name);
        }

        $model->order = (Badge::query()->max('order') ?? 0) + 1;
        $model->earned_count = 0;

        if ($model->is_active === null) {
            $model->is_active = true;
        }

        if ($model->is_visible === null) {
            $model->is_visible = true;
        }

        return $model;
    }

    public function saving(object $model, OriginalContext $context): ?object
    {
        $dirty = $model->getDirty();

        $validationData = [];

        foreach (['name', 'slug', 'description', 'icon', 'icon_color', 'background_color'] as $field) {
            if (array_key_exists($field, $dirty)) {
                $validationData[$field] = $dirty[$field];
            }
        }

        if (array_key_exists('trigger_config', $dirty) && $dirty['trigger_config'] !== null) {
            $triggerConfig = $dirty['trigger_config'];
            $validationData['trigger_config'] = is_string($triggerConfig) ? json_decode($triggerConfig, true) : $triggerConfig;
        }

        if (! empty($validationData)) {
            if ($model->exists) {
                $this->validator->setBadge($model);
            }
            $this->validator->assertValid($validationData);
        }

        return $model;
    }

    public static function resetCache(): void
    {
        static::$cachedTotalUsers = null;
        static::$cachedEarnedIds = null;
    }
}
