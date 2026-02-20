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
use Flarum\Post\Event\Revised;
use Flarum\Post\Post;
use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;

class EditCountMetric implements MetricInterface
{
    public function getType(): string
    {
        return 'edit_count';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.edit_count';
    }

    public function getEventTriggers(): array
    {
        return [
            Revised::class => fn ($event) => $event->actor,
        ];
    }

    public function getValue(User $user, array $config = []): int
    {
        $query = Post::where('edited_user_id', $user->id);

        if (isset($config['date_range'])) {
            if (! empty($config['date_range']['start'])) {
                $query->where('edited_at', '>=', Carbon::parse($config['date_range']['start']));
            }

            if (! empty($config['date_range']['end'])) {
                $query->where('edited_at', '<=', Carbon::parse($config['date_range']['end']));
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
        return [];
    }
}
