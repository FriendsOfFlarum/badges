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
use FoF\Badges\BadgeCategory;
use InvalidArgumentException;
use Tobscure\JsonApi\Relationship;

class BadgeCategorySerializer extends AbstractSerializer
{
    protected $type = 'badge-categories';

    /**
     * @param BadgeCategory $category
     * @return array<string, mixed>
     */
    protected function getDefaultAttributes($category): array
    {
        if (! ($category instanceof BadgeCategory)) {
            throw new InvalidArgumentException(
                get_class($this).' can only serialize instances of '.BadgeCategory::class
            );
        }

        // Use preloaded badges_count if available (via withCount), otherwise fallback to count()
        $badgeCount = $category->badges_count ?? $category->getAttribute('badges_count');
        if ($badgeCount === null) {
            $badgeCount = $category->badges()->count();
        }

        return [
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'isEnabled' => (bool) $category->is_enabled,
            'order' => (int) $category->order,
            'createdAt' => $this->formatDate($category->created_at),
            'badgeCount' => (int) $badgeCount,
        ];
    }

    /**
     * @param BadgeCategory $category
     */
    protected function badges($category): Relationship
    {
        return $this->hasMany($category, BadgeSerializer::class);
    }
}
