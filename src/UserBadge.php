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

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $badge_id
 * @property string $granted_by
 * @property int|null $granted_by_user_id
 * @property string|null $reason
 * @property bool $is_seen
 * @property bool $show_on_card
 * @property bool $is_primary
 * @property Carbon $earned_at
 *
 * @property-read User $user
 * @property-read Badge $badge
 * @property-read User|null $grantedByUser
 */
class UserBadge extends AbstractModel
{
    protected $table = 'fof_badge_user';

    /**
     * Disable timestamps as this table only has earned_at.
     */
    public $timestamps = false;

    public const GRANTED_BY_TRIGGER = 'trigger';
    public const GRANTED_BY_MANUAL = 'manual';
    public const GRANTED_BY_SYSTEM = 'system';
    public const GRANTED_BY_IMPORT = 'import';
    public const GRANTED_BY_API = 'api';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'badge_id' => 'integer',
        'granted_by_user_id' => 'integer',
        'is_seen' => 'boolean',
        'show_on_card' => 'boolean',
        'is_primary' => 'boolean',
        'earned_at' => 'datetime',
    ];

    /**
     * Create a new user badge instance.
     */
    public static function build(
        int $userId,
        int $badgeId,
        string $grantedBy = self::GRANTED_BY_TRIGGER,
        ?int $grantedByUserId = null
    ): static {
        $userBadge = new static();
        $userBadge->user_id = $userId;
        $userBadge->badge_id = $badgeId;
        $userBadge->granted_by = $grantedBy;
        $userBadge->granted_by_user_id = $grantedByUserId;
        $userBadge->is_seen = false;
        $userBadge->show_on_card = true;  // Show on card by default
        $userBadge->is_primary = false;
        $userBadge->earned_at = Carbon::now();

        return $userBadge;
    }

    /**
     * Get the user who earned this badge.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the badge that was earned.
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    /**
     * Get the user who manually granted this badge.
     */
    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /**
     * Mark this badge as seen by the user.
     */
    public function markAsSeen(): void
    {
        if (! $this->is_seen) {
            $this->is_seen = true;
            $this->save();
        }
    }

    /**
     * Check if badge was granted automatically.
     */
    public function isAutomatic(): bool
    {
        return in_array($this->granted_by, [self::GRANTED_BY_TRIGGER, self::GRANTED_BY_SYSTEM], true);
    }

    /**
     * Check if badge was granted manually.
     */
    public function isManual(): bool
    {
        return $this->granted_by === self::GRANTED_BY_MANUAL;
    }
}
