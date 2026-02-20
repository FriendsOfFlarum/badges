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

class PollsVotedMetric implements MetricInterface
{
    protected ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function getType(): string
    {
        return 'polls_voted';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.polls_voted';
    }

    public function getEventTriggers(): array
    {
        // PollVotesChanged is the primary event dispatched for all poll votes.
        // PollWasVoted is a legacy event only dispatched for single-vote polls.
        if (class_exists(\FoF\Polls\Events\PollVotesChanged::class)) {
            return [
                \FoF\Polls\Events\PollVotesChanged::class => fn ($event) => $event->actor,
            ];
        }

        return [];
    }

    public function getValue(User $user, array $config = []): int
    {
        // Count distinct polls the user has voted on (not individual option votes)
        $query = $this->db->table('poll_votes')
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

        return (int) $query->distinct()->count('poll_id');
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
