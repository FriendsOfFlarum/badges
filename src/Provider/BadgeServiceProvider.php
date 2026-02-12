<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Provider;

use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\User\User;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\Service\BadgeRecalculationOptimizer;
use FoF\Badges\Service\BadgeRecalculationService;
use FoF\Badges\Trigger\Metric;
use FoF\Badges\Trigger\MetricManager;
use FoF\Badges\Trigger\TriggerEvaluator;

class BadgeServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        // Register MetricManager as singleton
        $this->container->singleton(MetricManager::class, function ($container) {
            $manager = new MetricManager($container->make(ExtensionManager::class));

            // Core metrics (no dependencies)
            $manager->register(new Metric\PostCountMetric());
            $manager->register(new Metric\DiscussionCountMetric());
            $manager->register(new Metric\MemberDaysMetric());
            $manager->register(new Metric\HasAvatarMetric());
            $manager->register(new Metric\EditCountMetric());

            // Likes metrics (requires flarum/likes)
            if (class_exists(\Flarum\Likes\Event\PostWasLiked::class)) {
                $manager->register($container->make(Metric\LikesReceivedMetric::class));
                $manager->register($container->make(Metric\LikesGivenMetric::class));
            }

            // Tag metrics (requires flarum/tags)
            if (class_exists(\Flarum\Tags\Tag::class)) {
                $manager->register(new Metric\TagPostsMetric());
            }

            // Bio metric (requires fof/user-bio)
            // Registered unconditionally, isAvailable() checks if extension is enabled
            $manager->register(new Metric\HasBioMetric());

            // Nickname metric (requires flarum/nicknames)
            // Registered unconditionally, isAvailable() checks if extension is enabled
            $manager->register(new Metric\HasNicknameMetric());

            // Best Answer metric (requires fof/best-answer)
            // Registered unconditionally, isAvailable() checks if extension is enabled
            $manager->register($container->make(Metric\BestAnswersReceivedMetric::class));

            // Upload metric (requires fof/upload)
            // Registered unconditionally, isAvailable() checks if extension is enabled
            $manager->register($container->make(Metric\FilesUploadedMetric::class));

            // Polls metrics (requires fof/polls)
            // Registered unconditionally, isAvailable() checks if extension is enabled
            $manager->register($container->make(Metric\PollsCreatedMetric::class));
            $manager->register($container->make(Metric\PollsVotedMetric::class));

            // Byobu metric (requires fof/byobu)
            $manager->register($container->make(Metric\PrivateDiscussionsMetric::class));

            // Reactions metrics (requires fof/reactions)
            $manager->register($container->make(Metric\ReactionsReceivedMetric::class));
            $manager->register($container->make(Metric\ReactionsGivenMetric::class));

            // Gamification metrics (requires fof/gamification)
            $manager->register($container->make(Metric\UpvotesReceivedMetric::class));
            $manager->register($container->make(Metric\UpvotesGivenMetric::class));
            $manager->register($container->make(Metric\DownvotesReceivedMetric::class));
            $manager->register($container->make(Metric\DownvotesGivenMetric::class));

            return $manager;
        });

        // Register BadgeRecalculationService as singleton
        $this->container->singleton(BadgeRecalculationService::class);

        // Register BadgeRecalculationOptimizer as singleton
        $this->container->singleton(BadgeRecalculationOptimizer::class);
    }

    public function boot(): void
    {
        $this->registerBioChangeListener();
        $this->registerNicknameChangeListener();
    }

    /**
     * Register User model saved event listener for bio changes.
     * Only active if fof/user-bio extension is enabled.
     *
     * Note: This uses Eloquent's User::saved() hook which has no Flarum Extend equivalent,
     * so it must remain in the ServiceProvider.
     */
    protected function registerBioChangeListener(): void
    {
        if (! $this->container->make(ExtensionManager::class)->isEnabled('fof-user-bio')) {
            return;
        }

        User::saved(function (User $user) {
            // Only trigger if bio was actually changed
            if ($user->wasChanged('bio')) {
                /** @var TriggerEvaluator $evaluator */
                $evaluator = $this->container->make(TriggerEvaluator::class);
                /** @var BadgeAwarder $awarder */
                $awarder = $this->container->make(BadgeAwarder::class);

                $badges = $evaluator->evaluateForEvent($user, 'user_bio_changed');
                foreach ($badges as $badge) {
                    $awarder->award($user, $badge);
                }
            }
        });
    }

    /**
     * Register User model saved event listener for nickname changes.
     * Only active if flarum/nicknames extension is enabled.
     *
     * Note: This uses Eloquent's User::saved() hook which has no Flarum Extend equivalent,
     * so it must remain in the ServiceProvider.
     */
    protected function registerNicknameChangeListener(): void
    {
        if (! $this->container->make(ExtensionManager::class)->isEnabled('flarum-nicknames')) {
            return;
        }

        User::saved(function (User $user) {
            // Only trigger if nickname was actually changed
            if ($user->wasChanged('nickname')) {
                /** @var TriggerEvaluator $evaluator */
                $evaluator = $this->container->make(TriggerEvaluator::class);
                /** @var BadgeAwarder $awarder */
                $awarder = $this->container->make(BadgeAwarder::class);

                $badges = $evaluator->evaluateForEvent($user, 'user_nickname_changed');
                foreach ($badges as $badge) {
                    $awarder->award($user, $badge);
                }
            }
        });
    }
}
