<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Listener;

use Flarum\Discussion\Event\Started;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Flarum\User\Event\AvatarChanged;
use Flarum\User\Event\LoggedIn;
use Flarum\User\User;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\Trigger\TriggerEvaluator;

class EvaluateBadges
{
    protected TriggerEvaluator $evaluator;
    protected BadgeAwarder $awarder;

    public function __construct(TriggerEvaluator $evaluator, BadgeAwarder $awarder)
    {
        $this->evaluator = $evaluator;
        $this->awarder = $awarder;
    }

    /**
     * Handle an event and evaluate badges for the relevant user.
     */
    public function handle(object $event): void
    {
        // Handle PostWasLiked specially - evaluates both post author and liker
        if ($this->isPostWasLikedEvent($event)) {
            $this->handlePostWasLiked($event);

            return;
        }

        // Handle PostWasReacted specially - evaluates both post author and reactor
        if ($this->isPostWasReactedEvent($event)) {
            $this->handlePostWasReacted($event);

            return;
        }

        // Handle PostWasVoted specially - evaluates both post author and voter
        if ($this->isPostWasVotedEvent($event)) {
            $this->handlePostWasVoted($event);

            return;
        }

        $user = $this->extractUser($event);

        if (! $user || ! $user->exists) {
            return;
        }

        $this->evaluateAndAwardBadges($user, get_class($event));
    }

    /**
     * Handle PostWasLiked event - evaluate badges for both post author and liker.
     */
    protected function handlePostWasLiked(object $event): void
    {
        $eventClass = get_class($event);

        // Load post author
        $post = $event->post;
        if (! $post->relationLoaded('user')) {
            $post->load('user');
        }

        $postAuthor = $post->user;

        // Evaluate for post author (likes_received metric)
        if ($postAuthor && $postAuthor->exists) {
            $this->evaluateAndAwardBadges($postAuthor, $eventClass);
        }

        // Evaluate for liker (likes_given metric) - skip if same as author
        $liker = $event->actor;
        if ($liker && $liker->exists && (! $postAuthor || $liker->id !== $postAuthor->id)) {
            $this->evaluateAndAwardBadges($liker, $eventClass);
        }
    }

    /**
     * Evaluate badges for a user based on an event and award any qualified badges.
     */
    protected function evaluateAndAwardBadges(User $user, string $eventClass): void
    {
        $badges = $this->evaluator->evaluateForEvent($user, $eventClass);

        foreach ($badges as $badge) {
            // award() returns [userBadge, wasCreated] but we don't need to track it here
            $this->awarder->award($user, $badge);
        }
    }

    /**
     * Extract the user from an event.
     */
    protected function extractUser(object $event): ?User
    {
        if ($event instanceof Posted) {
            $post = $event->post;
            if (! $post->relationLoaded('user')) {
                $post->load('user');
            }

            return $post->user;
        }

        if ($event instanceof Revised) {
            return $event->actor;
        }

        if ($event instanceof Started) {
            return $event->actor;
        }

        if ($event instanceof AvatarChanged) {
            return $event->user;
        }

        if ($event instanceof LoggedIn) {
            return $event->user;
        }

        // FoF Best Answer - post author receives the best answer
        if ($this->isBestAnswerSetEvent($event)) {
            $post = $event->post;
            if (! $post->relationLoaded('user')) {
                $post->load('user');
            }

            return $post->user;
        }

        // FoF Upload - actor is the user who uploaded the file
        if ($this->isFileWasSavedEvent($event)) {
            return $event->actor;
        }

        // FoF Polls - actor is the user who created the poll or changed votes
        if ($this->isPollWasCreatedEvent($event) || $this->isPollVotesChangedEvent($event)) {
            return $event->actor;
        }

        // FoF Byobu - actor is the user who created the private discussion
        if ($this->isByobuCreatedEvent($event)) {
            return $event->actor;
        }

        return null;
    }

    /**
     * Check if the event is BestAnswerSet.
     */
    protected function isBestAnswerSetEvent(object $event): bool
    {
        return class_exists(\FoF\BestAnswer\Events\BestAnswerSet::class)
            && $event instanceof \FoF\BestAnswer\Events\BestAnswerSet;
    }

    /**
     * Check if the event is FoF Upload WasSaved.
     */
    protected function isFileWasSavedEvent(object $event): bool
    {
        return class_exists(\FoF\Upload\Events\File\WasSaved::class)
            && $event instanceof \FoF\Upload\Events\File\WasSaved;
    }

    /**
     * Check if the event is FoF Polls PollWasCreated.
     */
    protected function isPollWasCreatedEvent(object $event): bool
    {
        return class_exists(\FoF\Polls\Events\PollWasCreated::class)
            && $event instanceof \FoF\Polls\Events\PollWasCreated;
    }

    /**
     * Check if the event is FoF Polls PollVotesChanged.
     */
    protected function isPollVotesChangedEvent(object $event): bool
    {
        return class_exists(\FoF\Polls\Events\PollVotesChanged::class)
            && $event instanceof \FoF\Polls\Events\PollVotesChanged;
    }

    /**
     * Check if the event is FoF Byobu Created.
     */
    protected function isByobuCreatedEvent(object $event): bool
    {
        return class_exists(\FoF\Byobu\Events\Created::class)
            && $event instanceof \FoF\Byobu\Events\Created;
    }

    /**
     * Check if the event is PostWasLiked.
     */
    protected function isPostWasLikedEvent(object $event): bool
    {
        return class_exists(\Flarum\Likes\Event\PostWasLiked::class)
            && $event instanceof \Flarum\Likes\Event\PostWasLiked;
    }

    /**
     * Check if the event is FoF Reactions PostWasReacted.
     */
    protected function isPostWasReactedEvent(object $event): bool
    {
        return class_exists(\FoF\Reactions\Event\PostWasReacted::class)
            && $event instanceof \FoF\Reactions\Event\PostWasReacted;
    }

    /**
     * Check if the event is FoF Gamification PostWasVoted.
     */
    protected function isPostWasVotedEvent(object $event): bool
    {
        return class_exists(\FoF\Gamification\Events\PostWasVoted::class)
            && $event instanceof \FoF\Gamification\Events\PostWasVoted;
    }

    /**
     * Handle PostWasVoted event - evaluate badges for both post author and voter.
     */
    protected function handlePostWasVoted(object $event): void
    {
        $eventClass = get_class($event);

        // Load post author via the vote's post
        $post = $event->vote->post;
        if (! $post->relationLoaded('user')) {
            $post->load('user');
        }

        $postAuthor = $post->user;

        // Evaluate for post author (upvotes_received / downvotes_received metric)
        if ($postAuthor && $postAuthor->exists) {
            $this->evaluateAndAwardBadges($postAuthor, $eventClass);
        }

        // Evaluate for voter (upvotes_given / downvotes_given metric) - skip if same as author
        $voter = $event->vote->user;
        if ($voter && $voter->exists && (! $postAuthor || $voter->id !== $postAuthor->id)) {
            $this->evaluateAndAwardBadges($voter, $eventClass);
        }
    }

    /**
     * Handle PostWasReacted event - evaluate badges for both post author and reactor.
     */
    protected function handlePostWasReacted(object $event): void
    {
        $eventClass = get_class($event);

        // Load post author
        $post = $event->post;
        if (! $post->relationLoaded('user')) {
            $post->load('user');
        }

        $postAuthor = $post->user;

        // Evaluate for post author (reactions_received metric)
        if ($postAuthor && $postAuthor->exists) {
            $this->evaluateAndAwardBadges($postAuthor, $eventClass);
        }

        // Evaluate for reactor (reactions_given metric) - skip if same as author
        $reactor = $event->user;
        if ($reactor && $reactor->exists && (! $postAuthor || $reactor->id !== $postAuthor->id)) {
            $this->evaluateAndAwardBadges($reactor, $eventClass);
        }
    }
}
