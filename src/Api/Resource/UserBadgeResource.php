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
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\UserBadge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends AbstractDatabaseResource<UserBadge>
 */
class UserBadgeResource extends AbstractDatabaseResource
{
    public function __construct(
        protected BadgeAwarder $awarder
    ) {
    }

    public function type(): string
    {
        return 'user-badges';
    }

    public function model(): string
    {
        return UserBadge::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $actor = $context->getActor();

        // Basic visibility: non-moderators can't see hidden badges of other users
        // More specific filtering (by user, badge, search) is handled by the Searcher/Filters
        if (! $actor->hasPermission('badges.moderate') && ! $actor->hasPermission('badges.giveManually')) {
            $query->where(function ($q) use ($actor) {
                $q->where('user_id', $actor->id)
                    ->orWhere('show_on_card', true);
            });
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(20, 100)
                ->defaultInclude(['badge', 'user'])
                ->defaultSort('-earnedAt')
                ->eagerLoad(['badge', 'user', 'badge.category']),
            Endpoint\Create::make()
                ->authenticated()
                ->can('create')
                ->action(function (Context $context) {
                    $actor = $context->getActor();
                    $data = $context->body();
                    $attributes = Arr::get($data, 'data.attributes', []);

                    $userId = (int) Arr::get($attributes, 'userId');
                    $badgeId = (int) Arr::get($attributes, 'badgeId');
                    $reason = Arr::get($attributes, 'reason');

                    $user = User::findOrFail($userId);
                    $badge = Badge::findOrFail($badgeId);

                    [$userBadge, $wasCreated] = $this->awarder->award(
                        $user,
                        $badge,
                        UserBadge::GRANTED_BY_MANUAL,
                        $actor
                    );

                    if (! $wasCreated) {
                        throw new ValidationException([
                            'badge' => 'User already has this badge.',
                        ]);
                    }

                    if ($reason) {
                        $userBadge->reason = $reason;
                        $userBadge->save();
                    }

                    $userBadge->load(['badge', 'user']);

                    return $userBadge;
                }),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete')
                ->action(function (Context $context) {
                    $userBadge = $context->model;

                    $this->awarder->revoke($userBadge);
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\DateTime::make('earnedAt')
                ->property('earned_at'),
            Schema\Str::make('grantedBy')
                ->property('granted_by'),
            Schema\Str::make('reason')
                ->writableOnCreate()
                ->nullable(),
            Schema\Boolean::make('isSeen')
                ->property('is_seen')
                ->writable(),
            Schema\Boolean::make('showOnCard')
                ->property('show_on_card'),
            Schema\Boolean::make('isPrimary')
                ->property('is_primary'),
            Schema\Relationship\ToOne::make('badge')
                ->type('badges')
                ->includable(),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('grantedByUser')
                ->type('users')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('earnedAt')
                ->column('earned_at'),
        ];
    }
}
