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

use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use FoF\Badges\BadgeCategory;
use FoF\Badges\BadgeCategoryValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends AbstractDatabaseResource<BadgeCategory>
 */
class BadgeCategoryResource extends AbstractDatabaseResource
{
    public function __construct(
        protected BadgeCategoryValidator $validator
    ) {
    }

    public function type(): string
    {
        return 'badge-categories';
    }

    public function model(): string
    {
        return BadgeCategory::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $actor = $context->getActor();

        $query->withCount('badges');

        if (! $actor->hasPermission('badges.moderate')) {
            $query->where('is_enabled', true);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultSort('order'),
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
            Schema\Boolean::make('isEnabled')
                ->property('is_enabled')
                ->writable(),
            Schema\Integer::make('order')
                ->writable(),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\Integer::make('badgeCount')
                ->get(function (BadgeCategory $category) {
                    $count = $category->badges_count ?? $category->getAttribute('badges_count');

                    return (int) ($count ?? $category->badges()->count());
                }),
            Schema\Relationship\ToMany::make('badges')
                ->type('badges')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('order'),
            SortColumn::make('name'),
            SortColumn::make('createdAt')
                ->column('created_at'),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        if (empty($model->slug)) {
            $model->slug = Str::slug($model->name);
        }

        $model->order = (BadgeCategory::query()->max('order') ?? 0) + 1;

        if ($model->is_enabled === null) {
            $model->is_enabled = true;
        }

        return $model;
    }

    public function saving(object $model, OriginalContext $context): ?object
    {
        $dirty = $model->getDirty();

        $validationData = [];

        foreach (['name', 'slug', 'description'] as $field) {
            if (array_key_exists($field, $dirty)) {
                $validationData[$field] = $dirty[$field];
            }
        }

        if (! empty($validationData)) {
            if ($model->exists) {
                $this->validator->setCategory($model);
            }
            $this->validator->assertValid($validationData);
        }

        return $model;
    }
}
