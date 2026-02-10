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
use FoF\Badges\Listener\EvaluateBadges;
use FoF\Badges\Service\BadgeRecalculationOptimizer;
use FoF\Badges\Service\BadgeRecalculationService;
use FoF\Badges\Trigger\Metric;
use FoF\Badges\Trigger\MetricManager;
use FoF\Badges\Trigger\TriggerEvaluator;
use Illuminate\Contracts\Events\Dispatcher;

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
        $this->registerLikesListener();
        $this->registerBioChangeListener();
        $this->registerNicknameChangeListener();
        $this->registerBestAnswerListener();
        $this->registerUploadListener();
        $this->registerPollsListener();
        $this->registerByobuListener();
        $this->registerReactionsListener();
        $this->registerGamificationListener();
    }

    /**
     * Register PostWasLiked event listener if flarum/likes is available.
     * Note: We only listen for likes, not unlikes - badges are not revoked on unlike.
     */
    protected function registerLikesListener(): void
    {
        if (!class_exists(\Flarum\Likes\Event\PostWasLiked::class)) {
            return;
        }

        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $events->listen(
            \Flarum\Likes\Event\PostWasLiked::class,
            function (\Flarum\Likes\Event\PostWasLiked $event) {
                /** @var EvaluateBadges $listener */
                $listener = $this->container->make(EvaluateBadges::class);
                $listener->handle($event);
            }
        );
    }

    /**
     * Register User model saved event listener for bio changes.
     * Only active if fof/user-bio extension is enabled.
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

    /**
     * Register BestAnswerSet event listener if fof/best-answer is available.
     * Note: We only listen for BestAnswerSet, not Unset - badges are not revoked on unset.
     */
    protected function registerBestAnswerListener(): void
    {
        if (! class_exists(\FoF\BestAnswer\Events\BestAnswerSet::class)) {
            return;
        }

        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $events->listen(
            \FoF\BestAnswer\Events\BestAnswerSet::class,
            function (\FoF\BestAnswer\Events\BestAnswerSet $event) {
                /** @var EvaluateBadges $listener */
                $listener = $this->container->make(EvaluateBadges::class);
                $listener->handle($event);
            }
        );
    }

    /**
     * Register WasSaved event listener if fof/upload is available.
     * Triggers badge evaluation when a file is uploaded.
     */
    protected function registerUploadListener(): void
    {
        if (! class_exists(\FoF\Upload\Events\File\WasSaved::class)) {
            return;
        }

        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $events->listen(
            \FoF\Upload\Events\File\WasSaved::class,
            function (\FoF\Upload\Events\File\WasSaved $event) {
                /** @var EvaluateBadges $listener */
                $listener = $this->container->make(EvaluateBadges::class);
                $listener->handle($event);
            }
        );
    }

    /**
     * Register poll event listeners if fof/polls is available.
     * Listens for PollWasCreated and PollVotesChanged events.
     */
    protected function registerPollsListener(): void
    {
        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        // Poll created listener
        if (class_exists(\FoF\Polls\Events\PollWasCreated::class)) {
            $events->listen(
                \FoF\Polls\Events\PollWasCreated::class,
                function (\FoF\Polls\Events\PollWasCreated $event) {
                    /** @var EvaluateBadges $listener */
                    $listener = $this->container->make(EvaluateBadges::class);
                    $listener->handle($event);
                }
            );
        }

        // Poll votes changed listener - PollVotesChanged is the primary event for all votes.
        // PollWasVoted is a legacy event only dispatched for single-vote polls.
        if (class_exists(\FoF\Polls\Events\PollVotesChanged::class)) {
            $events->listen(
                \FoF\Polls\Events\PollVotesChanged::class,
                function (\FoF\Polls\Events\PollVotesChanged $event) {
                    /** @var EvaluateBadges $listener */
                    $listener = $this->container->make(EvaluateBadges::class);
                    $listener->handle($event);
                }
            );
        }
    }

    /**
     * Register Byobu Created event listener if fof/byobu is available.
     * Triggers badge evaluation when a private discussion is created.
     */
    protected function registerByobuListener(): void
    {
        if (! class_exists(\FoF\Byobu\Events\Created::class)) {
            return;
        }

        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $events->listen(
            \FoF\Byobu\Events\Created::class,
            function (\FoF\Byobu\Events\Created $event) {
                /** @var EvaluateBadges $listener */
                $listener = $this->container->make(EvaluateBadges::class);
                $listener->handle($event);
            }
        );
    }

    /**
     * Register PostWasReacted event listener if fof/reactions is available.
     * Triggers badge evaluation for both the post author (reactions_received)
     * and the reactor (reactions_given).
     */
    protected function registerReactionsListener(): void
    {
        if (! class_exists(\FoF\Reactions\Event\PostWasReacted::class)) {
            return;
        }

        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $events->listen(
            \FoF\Reactions\Event\PostWasReacted::class,
            function (\FoF\Reactions\Event\PostWasReacted $event) {
                /** @var EvaluateBadges $listener */
                $listener = $this->container->make(EvaluateBadges::class);
                $listener->handle($event);
            }
        );
    }

    /**
     * Register PostWasVoted event listener if fof/gamification is available.
     * Triggers badge evaluation for both the post author (upvotes/downvotes received)
     * and the voter (upvotes/downvotes given).
     */
    protected function registerGamificationListener(): void
    {
        if (! class_exists(\FoF\Gamification\Events\PostWasVoted::class)) {
            return;
        }

        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $events->listen(
            \FoF\Gamification\Events\PostWasVoted::class,
            function (\FoF\Gamification\Events\PostWasVoted $event) {
                /** @var EvaluateBadges $listener */
                $listener = $this->container->make(EvaluateBadges::class);
                $listener->handle($event);
            }
        );
    }
}
