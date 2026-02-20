<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Trigger\Metric;

use Carbon\Carbon;
use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;
use Illuminate\Database\ConnectionInterface;

class DownvotesReceivedMetric implements MetricInterface
{
    protected ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function getType(): string
    {
        return 'downvotes_received';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.downvotes_received';
    }

    public function getEventTriggers(): array
    {
        if (class_exists(\FoF\Gamification\Events\PostWasVoted::class)) {
            return [
                \FoF\Gamification\Events\PostWasVoted::class => fn ($event) => $event->vote->post->user,
            ];
        }

        return [];
    }

    public function getValue(User $user, array $config = []): int
    {
        $query = $this->db->table('post_votes')
            ->join('posts', 'post_votes.post_id', '=', 'posts.id')
            ->where('posts.user_id', $user->id)
            ->where('post_votes.value', -1);

        if (isset($config['date_range'])) {
            if (isset($config['date_range']['start'])) {
                $query->where('post_votes.created_at', '>=', Carbon::parse($config['date_range']['start']));
            }

            if (isset($config['date_range']['end'])) {
                $query->where('post_votes.created_at', '<=', Carbon::parse($config['date_range']['end']));
            }
        }

        return $query->count();
    }

    public function isBoolean(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [];
    }

    public function getExtensionDependencies(): array
    {
        return ['fof/gamification'];
    }
}
