<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Notification;

use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use FoF\Badges\UserBadge;

class BadgeEarnedBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(protected UserBadge $userBadge)
    {
    }

    /**
     * Get the subject of the notification (the user badge).
     */
    public function getSubject(): ?\Flarum\Database\AbstractModel
    {
        return $this->userBadge;
    }

    /**
     * Get the user who triggered the notification.
     * Returns grantedByUser for manual grants, null for system triggers.
     */
    public function getFromUser(): ?\Flarum\User\User
    {
        return $this->userBadge->grantedByUser;
    }

    /**
     * Get additional data for the notification.
     */
    public function getData(): mixed
    {
        $badge = $this->userBadge->badge;

        if (! $badge) {
            return [
                'badgeId' => null,
                'badgeName' => 'Unknown Badge',
                'badgeIcon' => 'fas fa-award',
                'badgeIconColor' => '#ffffff',
                'badgeBackgroundColor' => '#667eea',
            ];
        }

        return [
            'badgeId' => $badge->id,
            'badgeName' => $badge->name,
            'badgeIcon' => $badge->icon,
            'badgeIconColor' => $badge->icon_color,
            'badgeBackgroundColor' => $badge->background_color,
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
