<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\User\User;
use FoF\Badges\Badge;
use InvalidArgumentException;
use Tobscure\JsonApi\Relationship;

class BadgeSerializer extends AbstractSerializer
{
    protected $type = 'badges';

    /**
     * Cached total user count for rarity calculation (per-request cache).
     */
    protected static ?int $cachedTotalUsers = null;

    /**
     * @param Badge $badge
     * @return array<string, mixed>
     */
    protected function getDefaultAttributes($badge): array
    {
        if (! ($badge instanceof Badge)) {
            throw new InvalidArgumentException(
                get_class($this).' can only serialize instances of '.Badge::class
            );
        }

        $canModerate = $this->getActor()->hasPermission('badges.moderate');

        $attributes = [
            'name' => $badge->name,
            'slug' => $badge->slug,
            'description' => $badge->description,
            'icon' => $badge->icon,
            'iconColor' => $badge->icon_color,
            'backgroundColor' => $badge->background_color,
            'isActive' => (bool) $badge->is_active,
            'isVisible' => (bool) $badge->is_visible,
            'earnedCount' => (int) $badge->earned_count,
            'order' => (int) $badge->order,
            'createdAt' => $this->formatDate($badge->created_at),
            'rarity' => $this->calculateRarity($badge),
            'canEdit' => $canModerate,
            'categoryId' => $badge->category_id,
        ];

        // Only expose admin configuration to moderators
        if ($canModerate) {
            $attributes['triggerConfig'] = $badge->trigger_config;
            $attributes['actions'] = $badge->actions;
        }

        return $attributes;
    }

    /**
     * Calculate rarity percentage: earnedCount / total users * 100
     * Uses per-request cache to avoid N+1 queries.
     */
    protected function calculateRarity(Badge $badge): float
    {
        // Use cached total user count to avoid repeated queries
        if (self::$cachedTotalUsers === null) {
            self::$cachedTotalUsers = User::query()->count();
        }

        if (self::$cachedTotalUsers === 0) {
            return 0.0;
        }

        return min(100.0, round(($badge->earned_count / self::$cachedTotalUsers) * 100, 1));
    }

    /**
     * Reset the cached total user count (useful for testing).
     */
    public static function resetCache(): void
    {
        self::$cachedTotalUsers = null;
    }

    /**
     * @param Badge $badge
     */
    protected function category($badge): ?Relationship
    {
        return $this->hasOne($badge, BadgeCategorySerializer::class, 'category');
    }
}
