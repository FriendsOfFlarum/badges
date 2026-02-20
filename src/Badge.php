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
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $icon
 * @property string $icon_color
 * @property string $background_color
 * @property array|null $trigger_config
 * @property array|null $actions
 * @property bool $is_active
 * @property bool $is_visible
 * @property int $earned_count
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read BadgeCategory|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection|UserBadge[] $userBadges
 * @property-read \Illuminate\Database\Eloquent\Collection|User[] $users
 */
class Badge extends AbstractModel
{
    protected $table = 'fof_badges';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'category_id' => 'integer',
        'trigger_config' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'earned_count' => 'integer',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Create a new badge instance.
     */
    public static function build(
        string $name,
        string $slug,
        ?string $description = null,
        ?int $categoryId = null
    ): static {
        $badge = new static();
        $badge->name = $name;
        $badge->slug = $slug;
        $badge->description = $description;
        $badge->category_id = $categoryId;
        $badge->icon = 'fas fa-award';
        $badge->icon_color = '#ffffff';
        $badge->background_color = '#667eea';
        $badge->is_active = true;
        $badge->is_visible = true;
        $badge->earned_count = 0;
        $badge->order = 0;

        return $badge;
    }

    /**
     * Get the category this badge belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BadgeCategory::class, 'category_id');
    }

    /**
     * Get all user badge records for this badge.
     */
    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class, 'badge_id');
    }

    /**
     * Get all users who have earned this badge.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'fof_badge_user', 'badge_id', 'user_id')
            ->withPivot(['earned_at', 'granted_by', 'reason']);
    }

    /**
     * Check if this badge is manually assigned only (no trigger config).
     */
    public function isManual(): bool
    {
        return empty($this->trigger_config);
    }

    /**
     * Check if this badge has automatic triggers.
     */
    public function isAutomatic(): bool
    {
        return ! empty($this->trigger_config);
    }

    /**
     * Increment the earned count for rarity calculation.
     */
    public function incrementEarnedCount(): void
    {
        $this->increment('earned_count');
    }

    /**
     * Decrement the earned count for rarity calculation.
     */
    public function decrementEarnedCount(): void
    {
        if ($this->earned_count > 0) {
            $this->decrement('earned_count');
        }
    }

    /**
     * Sync earned_count with actual UserBadge records.
     *
     * @return int The corrected count
     */
    public function syncEarnedCount(): int
    {
        $actualCount = UserBadge::where('badge_id', $this->id)->count();
        $this->earned_count = $actualCount;
        $this->save();

        return $actualCount;
    }

    /**
     * Sync earned_count for all badges.
     * Optimized: Single aggregation query instead of N+1 queries.
     *
     * @return array Statistics about the sync operation
     */
    public static function syncAllEarnedCounts(): array
    {
        // Single query to get all badge counts
        $actualCounts = UserBadge::query()
            ->selectRaw('badge_id, COUNT(*) as count')
            ->groupBy('badge_id')
            ->pluck('count', 'badge_id')
            ->toArray();

        $badges = static::all();
        $corrected = 0;
        $total = $badges->count();

        foreach ($badges as $badge) {
            $oldCount = $badge->earned_count;
            $newCount = $actualCounts[$badge->id] ?? 0;

            if ($oldCount !== $newCount) {
                $badge->earned_count = $newCount;
                $badge->save();
                $corrected++;
            }
        }

        return [
            'total' => $total,
            'corrected' => $corrected,
        ];
    }
}
