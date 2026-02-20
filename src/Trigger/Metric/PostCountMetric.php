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

class PostCountMetric implements MetricInterface
{
    public function getType(): string
    {
        return 'post_count';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.post_count';
    }

    public function getEventTriggers(): array
    {
        return [
            Posted::class => fn ($event) => $event->actor,
        ];
    }

    public function getValue(User $user, array $config = []): int
    {
        // If date_range is present, count posts within that range
        if (isset($config['date_range'])) {
            $query = Post::where('user_id', $user->id)
                ->where('type', 'comment');

            if (isset($config['date_range']['start'])) {
                $query->where('created_at', '>=', Carbon::parse($config['date_range']['start']));
            }

            if (isset($config['date_range']['end'])) {
                $query->where('created_at', '<=', Carbon::parse($config['date_range']['end']));
            }

            return $query->count();
        }

        // Otherwise use cached count
        return (int) $user->comment_count;
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
        return [];
    }
}
