<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use FoF\Badges\UserBadge;
use InvalidArgumentException;
use Tobscure\JsonApi\Relationship;

class UserBadgeSerializer extends AbstractSerializer
{
    protected $type = 'user-badges';

    /**
     * @param UserBadge $userBadge
     * @return array<string, mixed>
     */
    protected function getDefaultAttributes($userBadge): array
    {
        if (! ($userBadge instanceof UserBadge)) {
            throw new InvalidArgumentException(
                get_class($this) . ' can only serialize instances of ' . UserBadge::class
            );
        }

        return [
            'grantedBy' => $userBadge->granted_by,
            'reason' => $userBadge->reason,
            'isSeen' => (bool) $userBadge->is_seen,
            'showOnCard' => (bool) $userBadge->show_on_card,
            'isPrimary' => (bool) $userBadge->is_primary,
            'earnedAt' => $this->formatDate($userBadge->earned_at),
        ];
    }

    /**
     * @param UserBadge $userBadge
     */
    protected function user($userBadge): Relationship
    {
        return $this->hasOne($userBadge, BasicUserSerializer::class);
    }

    /**
     * @param UserBadge $userBadge
     */
    protected function badge($userBadge): Relationship
    {
        return $this->hasOne($userBadge, BadgeSerializer::class);
    }

    /**
     * @param UserBadge $userBadge
     */
    protected function grantedByUser($userBadge): ?Relationship
    {
        if (!$userBadge->granted_by_user_id) {
            return null;
        }

        return $this->hasOne($userBadge, BasicUserSerializer::class, 'grantedByUser');
    }
}
