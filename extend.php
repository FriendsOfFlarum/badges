<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Extension\ExtensionManager;
use Flarum\Search\Database\DatabaseSearchDriver;
use Flarum\User\User;

return [
    // Frontend
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/badges', 'badges.index', Controller\BadgeOverviewController::class)
        ->route('/badges/{slug}', 'badges.show', Controller\BadgeOverviewController::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    // Locales
    new Extend\Locales(__DIR__.'/locale'),

    // API Resources
    new Extend\ApiResource(Api\Resource\BadgeResource::class),
    new Extend\ApiResource(Api\Resource\BadgeCategoryResource::class),
    new Extend\ApiResource(Api\Resource\UserBadgeResource::class),

    // Search drivers for filterable resources
    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(UserBadge::class, Search\UserBadgeSearcher::class)
        ->addFilter(Search\UserBadgeSearcher::class, Search\Filter\UserFilter::class)
        ->addFilter(Search\UserBadgeSearcher::class, Search\Filter\BadgeFilter::class)
        ->addFilter(Search\UserBadgeSearcher::class, Search\Filter\SearchFilter::class),

    // API Routes — custom (non-CRUD) endpoints only
    (new Extend\Routes('api'))
        ->post('/badge-categories/order', 'badge-categories.order', Api\Controller\OrderBadgeCategoriesController::class)
        ->post('/badges/order', 'badges.order', Api\Controller\OrderBadgesController::class)
        ->post('/user-badges/{id}/toggle', 'user-badges.toggle', Api\Controller\ToggleUserBadgeController::class)
        ->post('/badges/recalculate', 'badges.recalculate', Api\Controller\RecalculateBadgesController::class)
        ->get('/badges/recalculate/status', 'badges.recalculate.status', Api\Controller\RecalculationStatusController::class)
        ->get('/badges/recalculate/jobs', 'badges.recalculate.jobs', Api\Controller\ListRecalculationJobsController::class)
        ->post('/badges/recalculate/cancel', 'badges.recalculate.cancel', Api\Controller\CancelRecalculationController::class)
        ->post('/badges/sync-counts', 'badges.sync-counts', Api\Controller\SyncBadgeCountsController::class)
        ->post('/badges/install-defaults', 'badges.install-defaults', Api\Controller\InstallDefaultBadgesController::class),

    // User relationship to badges with count support
    (new Extend\Model(User::class))
        ->relationship('badges', function ($user) {
            return $user->belongsToMany(Badge::class, 'fof_badge_user', 'user_id', 'badge_id')
                ->withPivot(['earned_at', 'granted_by', 'reason']);
        })
        ->relationship('userBadges', function ($user) {
            return $user->hasMany(UserBadge::class, 'user_id');
        }),

    // Include user badges in discussion/post/user API responses
    (new Extend\ApiResource(Resource\DiscussionResource::class))
        ->endpoint(Endpoint\Index::class, function (Endpoint\Index $endpoint) {
            return $endpoint->eagerLoad([
                'user.userBadges', 'user.userBadges.badge',
                'lastPostedUser.userBadges', 'lastPostedUser.userBadges.badge',
                'mostRelevantPost.user.userBadges', 'mostRelevantPost.user.userBadges.badge',
            ]);
        })
        ->endpoint(Endpoint\Show::class, function (Endpoint\Show $endpoint) {
            return $endpoint->eagerLoad([
                'posts.user.userBadges', 'posts.user.userBadges.badge',
            ]);
        }),

    (new Extend\ApiResource(Resource\PostResource::class))
        ->endpoint(Endpoint\Index::class, function (Endpoint\Index $endpoint) {
            return $endpoint->eagerLoad(['user.userBadges', 'user.userBadges.badge']);
        })
        ->endpoint(Endpoint\Show::class, function (Endpoint\Show $endpoint) {
            return $endpoint->eagerLoad(['user.userBadges', 'user.userBadges.badge']);
        }),

    (new Extend\ApiResource(Resource\UserResource::class))
        ->endpoint(Endpoint\Show::class, function (Endpoint\Show $endpoint) {
            return $endpoint->eagerLoad(['userBadges', 'userBadges.badge']);
        })
        ->endpoint(Endpoint\Index::class, function (Endpoint\Index $endpoint) {
            return $endpoint->eagerLoad(['userBadges', 'userBadges.badge']);
        }),

    // Forum resource fields (replaces ForumSerializer attributes)
    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canViewBadges')
                ->get(fn ($model, Context $context) => $context->getActor()->hasPermission('badges.viewList')),
            Schema\Boolean::make('canViewUserBadges')
                ->get(fn ($model, Context $context) => $context->getActor()->hasPermission('badges.viewUserBadges')),
            Schema\Boolean::make('canModerateBadges')
                ->get(fn ($model, Context $context) => $context->getActor()->hasPermission('badges.moderate')),
            Schema\Boolean::make('canGiveBadges')
                ->get(fn ($model, Context $context) => $context->getActor()->hasPermission('badges.giveManually')),
            Schema\Integer::make('userCount')
                ->get(fn () => resolve(\Illuminate\Contracts\Cache\Repository::class)->remember('fof-badges.user_count', 3600, fn () => User::query()->count())),
            Schema\Boolean::make('likesExtensionEnabled')
                ->get(fn () => (bool) resolve(ExtensionManager::class)->isEnabled('flarum-likes')),
            Schema\Boolean::make('userBioExtensionEnabled')
                ->get(fn () => (bool) resolve(ExtensionManager::class)->isEnabled('fof-user-bio')),
            Schema\Boolean::make('nicknamesExtensionEnabled')
                ->get(fn () => (bool) resolve(ExtensionManager::class)->isEnabled('flarum-nicknames')),
            Schema\Boolean::make('bestAnswerExtensionEnabled')
                ->get(fn () => (bool) resolve(ExtensionManager::class)->isEnabled('fof-best-answer')),
            Schema\Boolean::make('uploadExtensionEnabled')
                ->get(fn () => (bool) resolve(ExtensionManager::class)->isEnabled('fof-upload')),
            Schema\Boolean::make('pollsExtensionEnabled')
                ->get(fn () => (bool) resolve(ExtensionManager::class)->isEnabled('fof-polls')),
            Schema\Boolean::make('byobuExtensionEnabled')
                ->get(fn () => (bool) resolve(ExtensionManager::class)->isEnabled('fof-byobu')),
            Schema\Boolean::make('reactionsExtensionEnabled')
                ->get(fn () => (bool) resolve(ExtensionManager::class)->isEnabled('fof-reactions')),
            Schema\Boolean::make('gamificationExtensionEnabled')
                ->get(fn () => (bool) resolve(ExtensionManager::class)->isEnabled('fof-gamification')),
            Schema\Boolean::make('badgeNewHighlightEnabled')
                ->get(fn () => (bool) resolve('flarum.settings')->get('fof-badges.new_badge_highlight', true)),
            Schema\Str::make('primaryBadgeDisplay')
                ->get(fn () => resolve('flarum.settings')->get('fof-badges.primary_badge_display', 'icon')),
            Schema\Boolean::make('showBadgesOnUserCard')
                ->get(fn () => (bool) resolve('flarum.settings')->get('fof-badges.show_badges_on_user_card', true)),
            Schema\Integer::make('badgeDisplayLimit')
                ->get(fn () => (int) resolve('flarum.settings')->get('fof-badges.badge_display_limit', 3)),
        ]),

    // User resource fields (replaces UserSerializer attributes)
    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(fn () => [
            Schema\Integer::make('badgeCount')
                ->get(function ($user, Context $context) {
                    return UserBadgeHelper::getUserBadges($user)->count();
                }),
            Schema\Str::make('primaryBadgeName')
                ->get(function ($user, Context $context) {
                    $primary = UserBadgeHelper::getPrimaryBadge($user);

                    return $primary && $primary->badge ? $primary->badge->name : null;
                })
                ->nullable(),
            Schema\Str::make('primaryBadgeIcon')
                ->get(function ($user, Context $context) {
                    $primary = UserBadgeHelper::getPrimaryBadge($user);

                    return $primary && $primary->badge ? $primary->badge->icon : null;
                })
                ->nullable(),
            Schema\Arr::make('visibleBadges')
                ->get(function ($user, Context $context) {
                    $settings = resolve('flarum.settings');
                    $displayLimit = (int) $settings->get('fof-badges.badge_display_limit', 3);

                    if ($displayLimit <= 0) {
                        return null;
                    }

                    $userBadges = UserBadgeHelper::getUserBadges($user);

                    if ($userBadges->isEmpty()) {
                        return null;
                    }

                    return $userBadges
                        ->filter(fn (UserBadge $ub) => $ub->show_on_card && $ub->badge !== null)
                        ->sort(function (UserBadge $a, UserBadge $b) {
                            if ($a->is_primary && ! $b->is_primary) {
                                return -1;
                            }
                            if (! $a->is_primary && $b->is_primary) {
                                return 1;
                            }

                            return ($a->badge->earned_count ?? PHP_INT_MAX) <=> ($b->badge->earned_count ?? PHP_INT_MAX);
                        })
                        ->take($displayLimit)
                        ->map(fn (UserBadge $ub) => ['name' => $ub->badge->name, 'icon' => $ub->badge->icon])
                        ->values()
                        ->toArray();
                })
                ->nullable(),
        ]),

    // Event listeners for badge triggers
    (new Extend\Event())
        ->listen(\Flarum\Post\Event\Posted::class, Listener\EvaluateBadges::class)
        ->listen(\Flarum\Post\Event\Revised::class, Listener\EvaluateBadges::class)
        ->listen(\Flarum\Discussion\Event\Started::class, Listener\EvaluateBadges::class)
        ->listen(\Flarum\User\Event\AvatarChanged::class, Listener\EvaluateBadges::class)
        ->listen(\Flarum\User\Event\LoggedIn::class, Listener\EvaluateBadges::class),

    // Conditional event listeners for optional extensions
    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-likes', fn () => [
            (new Extend\Event())
                ->listen(\Flarum\Likes\Event\PostWasLiked::class, Listener\EvaluateBadges::class),
        ])
        ->whenExtensionEnabled('fof-best-answer', fn () => [
            (new Extend\Event())
                ->listen(\FoF\BestAnswer\Events\BestAnswerSet::class, Listener\EvaluateBadges::class),
        ])
        ->whenExtensionEnabled('fof-upload', fn () => [
            (new Extend\Event())
                ->listen(\FoF\Upload\Events\File\WasSaved::class, Listener\EvaluateBadges::class),
        ])
        ->whenExtensionEnabled('fof-polls', fn () => [
            (new Extend\Event())
                ->listen(\FoF\Polls\Events\PollWasCreated::class, Listener\EvaluateBadges::class)
                ->listen(\FoF\Polls\Events\PollVotesChanged::class, Listener\EvaluateBadges::class),
        ])
        ->whenExtensionEnabled('fof-byobu', fn () => [
            (new Extend\Event())
                ->listen(\FoF\Byobu\Events\Created::class, Listener\EvaluateBadges::class),
        ])
        ->whenExtensionEnabled('fof-reactions', fn () => [
            (new Extend\Event())
                ->listen(\FoF\Reactions\Event\PostWasReacted::class, Listener\EvaluateBadges::class),
        ])
        ->whenExtensionEnabled('fof-gamification', fn () => [
            (new Extend\Event())
                ->listen(\FoF\Gamification\Events\PostWasVoted::class, Listener\EvaluateBadges::class),
        ]),

    // Notification
    (new Extend\Notification())
        ->type(Notification\BadgeEarnedBlueprint::class, ['alert']),

    // Service provider for MetricManager and conditional extension support
    (new Extend\ServiceProvider())
        ->register(Provider\BadgeServiceProvider::class),

    // Settings defaults
    (new Extend\Settings())
        ->default('fof-badges.show_badges_on_user_card', true)
        ->default('fof-badges.new_badge_highlight', true)
        ->default('fof-badges.primary_badge_display', 'icon')
        ->default('fof-badges.badge_display_limit', 3),

    // Console command
    (new Extend\Console())
        ->command(Console\RecalculateBadgesCommand::class),

    // Model policies for authorization
    (new Extend\Policy())
        ->modelPolicy(Badge::class, Access\BadgePolicy::class)
        ->modelPolicy(BadgeCategory::class, Access\BadgeCategoryPolicy::class)
        ->modelPolicy(UserBadge::class, Access\UserBadgePolicy::class),
];
