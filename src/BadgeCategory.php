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

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_enabled
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|Badge[] $badges
 * @property-read int|null $badges_count
 */
class BadgeCategory extends AbstractModel
{
    protected $table = 'fof_badge_cat';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Create a new badge category instance.
     */
    public static function build(string $name, string $slug, ?string $description = null): static
    {
        $category = new static();
        $category->name = $name;
        $category->slug = $slug;
        $category->description = $description;
        $category->is_enabled = true;
        $category->order = 0;

        return $category;
    }

    /**
     * Get the badges in this category.
     */
    public function badges(): HasMany
    {
        return $this->hasMany(Badge::class, 'category_id');
    }

    /**
     * Get enabled badges in this category.
     */
    public function activeBadges(): HasMany
    {
        return $this->badges()->where('is_active', true);
    }

    /**
     * Get visible badges in this category.
     */
    public function visibleBadges(): HasMany
    {
        return $this->badges()->where('is_visible', true);
    }
}
