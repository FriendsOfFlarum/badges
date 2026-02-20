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

use Flarum\Api\Controller as FlarumController;
use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Extend;
use Flarum\Extension\ExtensionManager;
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

    // API Routes
    (new Extend\Routes('api'))
        // Badge Categories
        ->get('/badge-categories', 'badge-categories.index', Api\Controller\ListBadgeCategoriesController::class)
        ->post('/badge-categories', 'badge-categories.create', Api\Controller\CreateBadgeCategoryController::class)
        ->patch('/badge-categories/{id}', 'badge-categories.update', Api\Controller\UpdateBadgeCategoryController::class)
        ->delete('/badge-categories/{id}', 'badge-categories.delete', Api\Controller\DeleteBadgeCategoryController::class)
        ->post('/badge-categories/order', 'badge-categories.order', Api\Controller\OrderBadgeCategoriesController::class)

        // Badges
        ->get('/badges', 'badges.index', Api\Controller\ListBadgesController::class)
        ->post('/badges', 'badges.create', Api\Controller\CreateBadgeController::class)
        ->get('/badges/{id}', 'badges.show', Api\Controller\ShowBadgeController::class)
        ->get('/badges/{id}/holders', 'badges.holders', Api\Controller\ListBadgeHoldersController::class)
        ->patch('/badges/{id}', 'badges.update', Api\Controller\UpdateBadgeController::class)
        ->delete('/badges/{id}', 'badges.delete', Api\Controller\DeleteBadgeController::class)
        ->post('/badges/order', 'badges.order', Api\Controller\OrderBadgesController::class)

        // User Badges
        ->get('/user-badges', 'user-badges.index', Api\Controller\ListUserBadgesController::class)
        ->post('/user-badges', 'user-badges.create', Api\Controller\AssignBadgeController::class)
        ->delete('/user-badges/{id}', 'user-badges.delete', Api\Controller\RevokeBadgeController::class)
        ->post('/user-badges/{id}/toggle', 'user-badges.toggle', Api\Controller\ToggleUserBadgeController::class)

        // Recalculate badges
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

    // Eager-load user badges on core controllers to avoid N+1 queries in UserSerializer.
    // Without this, the attributes closure queries per-user. With eager loading,
    // Eloquent preloads all badges in a single query and the closure reads from memory.
    (new Extend\ApiController(FlarumController\ListDiscussionsController::class))
        ->addInclude([
            'user.userBadges', 'user.userBadges.badge',
            'lastPostedUser.userBadges', 'lastPostedUser.userBadges.badge',
            'mostRelevantPost.user.userBadges', 'mostRelevantPost.user.userBadges.badge',
        ]),

    (new Extend\ApiController(FlarumController\ShowDiscussionController::class))
        ->addInclude(['posts.user.userBadges', 'posts.user.userBadges.badge']),

    (new Extend\ApiController(FlarumController\ListPostsController::class))
        ->addInclude(['user.userBadges', 'user.userBadges.badge']),

    (new Extend\ApiController(FlarumController\ShowPostController::class))
        ->addInclude(['user.userBadges', 'user.userBadges.badge']),

    (new Extend\ApiController(FlarumController\ShowUserController::class))
        ->addInclude(['userBadges', 'userBadges.badge']),

    (new Extend\ApiController(FlarumController\ListUsersController::class))
        ->addInclude(['userBadges', 'userBadges.badge']),

    // Forum permissions and stats
    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attributes(function (ForumSerializer $serializer) {
            $actor = $serializer->getActor();
            $settings = resolve('flarum.settings');

            // Cache user count for 1 hour to avoid repeated queries
            $userCount = resolve('cache')->remember('fof-badges.user_count', 3600, function () {
                return User::query()->count();
            });

            // Check if optional extensions are enabled
            $extensions = resolve(ExtensionManager::class);
            $likesEnabled = $extensions->isEnabled('flarum-likes');
            $userBioEnabled = $extensions->isEnabled('fof-user-bio');
            $nicknamesEnabled = $extensions->isEnabled('flarum-nicknames');
            $bestAnswerEnabled = $extensions->isEnabled('fof-best-answer');
            $uploadEnabled = $extensions->isEnabled('fof-upload');
            $pollsEnabled = $extensions->isEnabled('fof-polls');
            $byobuEnabled = $extensions->isEnabled('fof-byobu');
            $reactionsEnabled = $extensions->isEnabled('fof-reactions');
            $gamificationEnabled = $extensions->isEnabled('fof-gamification');

            return [
                'canViewBadges' => $actor->hasPermission('badges.viewList'),
                'canViewUserBadges' => $actor->hasPermission('badges.viewUserBadges'),
                'canModerateBadges' => $actor->hasPermission('badges.moderate'),
                'canGiveBadges' => $actor->hasPermission('badges.giveManually'),
                'userCount' => $userCount,
                'likesExtensionEnabled' => (bool) $likesEnabled,
                'userBioExtensionEnabled' => (bool) $userBioEnabled,
                'nicknamesExtensionEnabled' => (bool) $nicknamesEnabled,
                'bestAnswerExtensionEnabled' => (bool) $bestAnswerEnabled,
                'uploadExtensionEnabled' => (bool) $uploadEnabled,
                'pollsExtensionEnabled' => (bool) $pollsEnabled,
                'byobuExtensionEnabled' => (bool) $byobuEnabled,
                'reactionsExtensionEnabled' => (bool) $reactionsEnabled,
                'gamificationExtensionEnabled' => (bool) $gamificationEnabled,
                'badgeNewHighlightEnabled' => (bool) $settings->get('fof-badges.new_badge_highlight', true),
                'primaryBadgeDisplay' => $settings->get('fof-badges.primary_badge_display', 'icon'),
                'showBadgesOnUserCard' => (bool) $settings->get('fof-badges.show_badges_on_user_card', true),
                'badgeDisplayLimit' => (int) $settings->get('fof-badges.badge_display_limit', 3),
            ];
        }),

    // User badges count attribute and badge list for display
    (new Extend\ApiSerializer(UserSerializer::class))
        ->attributes(function (UserSerializer $serializer, User $user) {
            $settings = resolve('flarum.settings');
            $displayLimit = (int) $settings->get('fof-badges.badge_display_limit', 3);

            $attributes = [
                'badgeCount' => 0,
                'primaryBadgeName' => null,
                'primaryBadgeIcon' => null,
                'visibleBadges' => null,
            ];

            // Use eager-loaded relationship if available (via addInclude on controllers),
            // otherwise query and cache on the model to avoid re-querying when
            // tobscure/json-api re-invokes getAttributes() for merged Resources.
            if ($user->relationLoaded('userBadges')) {
                $userBadges = $user->getRelation('userBadges');

                // Ensure nested badge relationship is loaded
                if ($userBadges->isNotEmpty() && ! $userBadges->first()->relationLoaded('badge')) {
                    $userBadges->load('badge');
                }
            } else {
                $userBadges = UserBadge::where('user_id', $user->id)
                    ->with('badge')
                    ->get();
                $user->setRelation('userBadges', $userBadges);
            }

            $badgeCount = $userBadges->count();
            $attributes['badgeCount'] = $badgeCount;

            if ($badgeCount === 0) {
                return $attributes;
            }

            // Find primary badge (is_primary=true) or rarest badge (lowest earned_count)
            $primaryBadge = $userBadges->firstWhere('is_primary', true);
            if (! $primaryBadge) {
                $primaryBadge = $userBadges
                    ->filter(fn ($ub) => $ub->badge !== null)
                    ->sortBy(fn ($ub) => $ub->badge->earned_count ?? PHP_INT_MAX)
                    ->first();
            }

            if ($primaryBadge && $primaryBadge->badge) {
                $attributes['primaryBadgeName'] = $primaryBadge->badge->name;
                $attributes['primaryBadgeIcon'] = $primaryBadge->badge->icon;
            }

            // Get visible badges for card/footer display
            if ($displayLimit > 0) {
                $visibleBadges = $userBadges
                    ->filter(fn ($ub) => $ub->show_on_card && $ub->badge !== null)
                    ->sort(function ($a, $b) {
                        // Primary badge always comes first
                        if ($a->is_primary && ! $b->is_primary) {
                            return -1;
                        }
                        if (! $a->is_primary && $b->is_primary) {
                            return 1;
                        }

                        // Then sort by rarity (lowest earned_count = rarest)
                        return ($a->badge->earned_count ?? PHP_INT_MAX) <=> ($b->badge->earned_count ?? PHP_INT_MAX);
                    })
                    ->take($displayLimit)
                    ->map(fn ($ub) => ['name' => $ub->badge->name, 'icon' => $ub->badge->icon])
                    ->values()
                    ->toArray();

                $attributes['visibleBadges'] = $visibleBadges;
            }

            return $attributes;
        }),

    // Event listeners for badge triggers
    // Note: Likes event listeners (PostWasLiked, PostWasUnliked) are registered
    // in BadgeServiceProvider::boot() to ensure proper class loading
    // Note: Bio changes are handled via Model::afterSave above
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
        ->type(Notification\BadgeEarnedBlueprint::class, Api\Serializer\UserBadgeSerializer::class, ['alert']),

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
