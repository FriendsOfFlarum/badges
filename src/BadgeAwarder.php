<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges;

use Flarum\Group\Group;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use FoF\Badges\Event\BadgeAwarded;
use FoF\Badges\Event\BadgeRevoked;
use FoF\Badges\Notification\BadgeEarnedBlueprint;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\QueryException;

class BadgeAwarder
{
    protected NotificationSyncer $notifications;
    protected Dispatcher $events;

    public function __construct(NotificationSyncer $notifications, Dispatcher $events)
    {
        $this->notifications = $notifications;
        $this->events = $events;
    }

    /**
     * Award a badge to a user.
     *
     * @param User $user The user to award the badge to
     * @param Badge $badge The badge to award
     * @param string $grantedBy How the badge was granted (use UserBadge::GRANTED_BY_* constants)
     * @param User|null $grantedByUser The user who granted the badge (for manual grants)
     * @return array{0: UserBadge, 1: bool} [userBadge, wasCreated]
     */
    public function award(
        User $user,
        Badge $badge,
        string $grantedBy = UserBadge::GRANTED_BY_TRIGGER,
        ?User $grantedByUser = null
    ): array {
        // Check if user already has this badge
        $existing = UserBadge::where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        // Create user badge record with race condition protection.
        // If two concurrent requests pass the check above, the unique
        // constraint on (user_id, badge_id) will prevent duplicates.
        $userBadge = UserBadge::build(
            $user->id,
            $badge->id,
            $grantedBy,
            $grantedByUser?->id
        );

        try {
            $userBadge->save();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                $existing = UserBadge::where('user_id', $user->id)
                    ->where('badge_id', $badge->id)
                    ->first();

                return [$existing, false];
            }

            throw $e;
        }

        // Increment earned count
        $badge->incrementEarnedCount();

        // Execute badge actions
        $this->executeActions($user, $badge);

        // Send notification
        $this->sendNotification($user, $userBadge, $badge);

        // Dispatch event
        $this->events->dispatch(new BadgeAwarded(
            $user,
            $badge,
            $userBadge,
            $grantedBy,
            $grantedByUser
        ));

        return [$userBadge, true];
    }

    /**
     * Revoke a badge from a user.
     *
     * @param UserBadge $userBadge The user badge to revoke
     */
    public function revoke(UserBadge $userBadge): void
    {
        $badge = $userBadge->badge;
        $user = $userBadge->user;

        // Delete the user badge
        $userBadge->delete();

        // Decrement earned count
        if ($badge) {
            $badge->decrementEarnedCount();
        }

        // Dispatch event
        if ($user && $badge) {
            $this->events->dispatch(new BadgeRevoked($user, $badge, 'revoked'));
        }
    }

    /**
     * Execute only the actions for a badge (without awarding).
     * Used for re-applying actions to existing badge holders.
     *
     * @param User $user The user who has the badge
     * @param Badge $badge The badge
     */
    public function executeActionsOnly(User $user, Badge $badge): void
    {
        $this->executeActions($user, $badge);
    }

    /**
     * Execute actions defined in the badge configuration.
     *
     * @param User $user The user who earned the badge
     * @param Badge $badge The badge that was earned
     */
    protected function executeActions(User $user, Badge $badge): void
    {
        $actions = $badge->actions;

        if (empty($actions)) {
            return;
        }

        // Add user to group if specified
        if (isset($actions['add_to_group']) && $actions['add_to_group']) {
            $groupId = (int) $actions['add_to_group'];
            $group = Group::find($groupId);

            if ($group && ! $user->groups->contains($groupId)) {
                $user->groups()->attach($groupId);
            }
        }
    }

    /**
     * Send notification to the user about earning the badge.
     *
     * @param User $user The user who earned the badge
     * @param UserBadge $userBadge The user badge record
     * @param Badge $badge The badge that was earned
     */
    protected function sendNotification(User $user, UserBadge $userBadge, Badge $badge): void
    {
        $actions = $badge->actions;

        // Check if notifications are enabled (default to true)
        $sendNotification = $actions['send_notification'] ?? true;

        if (! $sendNotification) {
            return;
        }

        $this->notifications->sync(
            new BadgeEarnedBlueprint($userBadge),
            [$user]
        );
    }
}
