<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use FoF\Badges\UserBadge;

class BadgeEarnedBlueprint implements BlueprintInterface
{
    protected UserBadge $userBadge;

    public function __construct(UserBadge $userBadge)
    {
        $this->userBadge = $userBadge;
    }

    /**
     * Get the subject of the notification (the user badge).
     */
    public function getSubject(): UserBadge
    {
        return $this->userBadge;
    }

    /**
     * Get the user who triggered the notification.
     * Returns grantedByUser for manual grants, null for system triggers.
     */
    public function getFromUser(): ?User
    {
        return $this->userBadge->grantedByUser;
    }

    /**
     * Get additional data for the notification.
     */
    public function getData(): array
    {
        $badge = $this->userBadge->badge;

        return [
            'badgeId' => $badge ? $badge->id : null,
            'badgeName' => $badge ? $badge->name : null,
            'badgeIcon' => $badge ? $badge->icon : null,
            'badgeIconColor' => $badge ? $badge->icon_color : null,
            'badgeBackgroundColor' => $badge ? $badge->background_color : null,
        ];
    }

    /**
     * Get the type of notification.
     */
    public static function getType(): string
    {
        return 'badgeEarned';
    }

    /**
     * Get the model class for the subject.
     */
    public static function getSubjectModel(): string
    {
        return UserBadge::class;
    }
}
