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

class PollsCreatedMetric implements MetricInterface
{
    public function __construct(protected ConnectionInterface $db)
    {
    }

    public function getType(): string
    {
        return 'polls_created';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.polls_created';
    }

    public function getEventTriggers(): array
    {
        // PollWasCreated event - actor is the user who created the poll
        if (class_exists(\FoF\Polls\Events\PollWasCreated::class)) {
            return [
                \FoF\Polls\Events\PollWasCreated::class => fn ($event) => $event->actor,
            ];
        }

        return [];
    }

    public function getValue(User $user, array $config = []): int
    {
        // Query polls table directly
        $query = $this->db->table('polls')
            ->where('user_id', $user->id);

        // Apply date range filter if present
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
        return ['fof/polls'];
    }
}
