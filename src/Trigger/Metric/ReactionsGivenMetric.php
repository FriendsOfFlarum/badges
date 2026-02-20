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

class ReactionsGivenMetric implements MetricInterface
{
    protected ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function getType(): string
    {
        return 'reactions_given';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.reactions_given';
    }

    public function getEventTriggers(): array
    {
        if (class_exists(\FoF\Reactions\Event\PostWasReacted::class)) {
            return [
                \FoF\Reactions\Event\PostWasReacted::class => fn ($event) => $event->user,
            ];
        }

        return [];
    }

    public function getValue(User $user, array $config = []): int
    {
        $query = $this->db->table('post_reactions')
            ->where('user_id', $user->id);

        if (isset($config['date_range'])) {
            if (isset($config['date_range']['start']) && $config['date_range']['start']) {
                $query->where('created_at', '>=', Carbon::parse($config['date_range']['start']));
            }

            if (isset($config['date_range']['end']) && $config['date_range']['end']) {
                $query->where('created_at', '<=', Carbon::parse($config['date_range']['end']));
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
        return ['fof/reactions'];
    }
}
