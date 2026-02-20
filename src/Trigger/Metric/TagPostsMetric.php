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
use Flarum\Post\Event\Posted;
use Flarum\Post\Post;
use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;

class TagPostsMetric implements MetricInterface
{
    public function getType(): string
    {
        return 'tag_posts';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.tag_posts';
    }

    public function getEventTriggers(): array
    {
        return [
            Posted::class => fn ($event) => $event->actor,
        ];
    }

    public function getValue(User $user, array $config = []): int
    {
        $tagId = $config['tag_id'] ?? null;

        if (! $tagId) {
            return 0;
        }

        // Check if tags extension is available
        if (! class_exists(\Flarum\Tags\Tag::class)) {
            return 0;
        }

        // Count posts in discussions that have this tag
        $query = Post::where('posts.user_id', $user->id)
            ->where('posts.type', 'comment')
            ->join('discussion_tag', 'posts.discussion_id', '=', 'discussion_tag.discussion_id')
            ->where('discussion_tag.tag_id', $tagId);

        // If date_range is present, filter by post creation date
        if (isset($config['date_range'])) {
            if (isset($config['date_range']['start'])) {
                $query->where('posts.created_at', '>=', Carbon::parse($config['date_range']['start']));
            }

            if (isset($config['date_range']['end'])) {
                $query->where('posts.created_at', '<=', Carbon::parse($config['date_range']['end']));
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
        return [
            'tag_id' => [
                'type' => 'select',
                'required' => true,
            ],
        ];
    }

    public function getExtensionDependencies(): array
    {
        return ['flarum/tags'];
    }
}
