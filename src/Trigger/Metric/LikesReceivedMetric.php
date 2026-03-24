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

class LikesReceivedMetric implements MetricInterface
{
    public function __construct(protected ConnectionInterface $db)
    {
    }

    public function getType(): string
    {
        return 'likes_received';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.likes_received';
    }

    public function getEventTriggers(): array
    {
        // PostWasLiked event - user extractor gets the post author
        if (class_exists(\Flarum\Likes\Event\PostWasLiked::class)) {
            return [
                \Flarum\Likes\Event\PostWasLiked::class => fn ($event) => $event->post->user,
            ];
        }

        return [];
    }

    public function getValue(User $user, array $config = []): int
    {
        // Sum likes on user's posts
        $query = $this->db->table('post_likes')
            ->join('posts', 'post_likes.post_id', '=', 'posts.id')
            ->where('posts.user_id', $user->id);

        // If date_range is present, filter by when the like was given
        if (isset($config['date_range'])) {
            if (isset($config['date_range']['start'])) {
                $query->where('post_likes.created_at', '>=', Carbon::parse($config['date_range']['start']));
            }

            if (isset($config['date_range']['end'])) {
                $query->where('post_likes.created_at', '<=', Carbon::parse($config['date_range']['end']));
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
        return ['flarum/likes'];
    }
}
