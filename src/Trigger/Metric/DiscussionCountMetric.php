<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Trigger\Metric;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Started;
use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;

class DiscussionCountMetric implements MetricInterface
{
    public function getType(): string
    {
        return 'discussion_count';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.discussion_count';
    }

    public function getEventTriggers(): array
    {
        return [
            Started::class => fn ($event) => $event->actor,
        ];
    }

    public function getValue(User $user, array $config = []): int
    {
        // If date_range is present, count discussions within that range
        if (isset($config['date_range'])) {
            $query = Discussion::where('user_id', $user->id);

            if (isset($config['date_range']['start'])) {
                $query->where('created_at', '>=', Carbon::parse($config['date_range']['start']));
            }

            if (isset($config['date_range']['end'])) {
                $query->where('created_at', '<=', Carbon::parse($config['date_range']['end']));
            }

            return $query->count();
        }

        // Otherwise use cached count
        return (int) $user->discussion_count;
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
