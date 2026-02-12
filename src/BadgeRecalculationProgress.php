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
 * @property int|null $started_by_user_id
 * @property string $status
 * @property int $total_users
 * @property int $processed_users
 * @property int $total_badges
 * @property int $awarded
 * @property int $revoked
 * @property int $skipped
 * @property int $total_chunks
 * @property int $processed_chunks
 * @property int $chunk_size
 * @property int|null $badge_id
 * @property bool $no_revoke
 * @property bool $reapply_actions
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BadgeRecalculationProgress extends AbstractModel
{
    protected $table = 'fof_badge_recalc';
    public $timestamps = true;

    protected $casts = [
        'no_revoke' => 'boolean',
        'reapply_actions' => 'boolean',
        'total_users' => 'integer',
        'processed_users' => 'integer',
        'total_badges' => 'integer',
        'awarded' => 'integer',
        'revoked' => 'integer',
        'skipped' => 'integer',
        'total_chunks' => 'integer',
        'processed_chunks' => 'integer',
        'chunk_size' => 'integer',
    ];

    protected $dates = [
        'started_at',
        'completed_at',
        'created_at',
        'updated_at',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user who started this recalculation.
     */
    public function startedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    /**
     * Get the badge being recalculated, if specific.
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public static function createNew(
        ?User $actor,
        ?int $badgeId = null,
        bool $noRevoke = false,
        int $chunkSize = 100,
        bool $reapplyActions = false
    ): self {
        $progress = new static();
        $progress->started_by_user_id = $actor?->id;
        $progress->badge_id = $badgeId;
        $progress->no_revoke = $noRevoke;
        $progress->reapply_actions = $reapplyActions;
        $progress->chunk_size = $chunkSize;
        $progress->status = self::STATUS_PENDING;
        $progress->save();

        return $progress;
    }

    public function markAsRunning(int $totalUsers, int $totalBadges, int $totalChunks = 0): self
    {
        $this->status = self::STATUS_RUNNING;
        $this->total_users = $totalUsers;
        $this->total_badges = $totalBadges;
        $this->total_chunks = $totalChunks;
        $this->started_at = Carbon::now();
        $this->save();

        return $this;
    }

    public function updateProgress(int $processedUsers, int $awarded, int $revoked, int $skipped): self
    {
        $this->processed_users = $processedUsers;
        $this->awarded = $awarded;
        $this->revoked = $revoked;
        $this->skipped = $skipped;
        $this->save();

        return $this;
    }

    /**
     * Increment chunk progress atomically to handle concurrent chunk processing.
     */
    public function incrementChunkProgress(int $processedUsers, int $awarded, int $revoked, int $skipped): self
    {
        // Use raw SQL for atomic increment
        $connection = $this->getConnection();
        $table = $this->getTable();

        $connection->statement("
            UPDATE {$table}
            SET
                processed_users = processed_users + ?,
                processed_chunks = processed_chunks + 1,
                awarded = awarded + ?,
                revoked = revoked + ?,
                skipped = skipped + ?
            WHERE id = ?
        ", [$processedUsers, $awarded, $revoked, $skipped, $this->id]);

        $this->refresh();

        // Check if all chunks are processed
        if ($this->processed_chunks >= $this->total_chunks && $this->status === self::STATUS_RUNNING) {
            $this->markAsCompleted();
        }

        return $this;
    }

    public function markAsCompleted(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = Carbon::now();
        $this->save();

        return $this;
    }

    public function markAsFailed(string $errorMessage): self
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $errorMessage;
        $this->completed_at = Carbon::now();
        $this->save();

        return $this;
    }

    public function markAsCancelled(): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->completed_at = Carbon::now();
        $this->save();

        return $this;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getProgressPercentage(): float
    {
        $totalUsers = (int) $this->total_users;

        if ($totalUsers <= 0) {
            return 0.0;
        }

        return round(((int) $this->processed_users / $totalUsers) * 100, 1);
    }

    public function getChunkProgressPercentage(): float
    {
        $totalChunks = (int) $this->total_chunks;

        if ($totalChunks <= 0) {
            return 0.0;
        }

        return round(((int) $this->processed_chunks / $totalChunks) * 100, 1);
    }

    public static function hasActiveJob(): bool
    {
        return static::query()
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING])
            ->exists();
    }

    public static function getActiveJob(): ?self
    {
        return static::query()
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public static function getLatest(): ?self
    {
        return static::query()
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
