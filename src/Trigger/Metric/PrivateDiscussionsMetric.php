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
use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;
use Illuminate\Database\ConnectionInterface;

class PrivateDiscussionsMetric implements MetricInterface
{
    protected ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function getType(): string
    {
        return 'private_discussions_created';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.private_discussions_created';
    }

    public function getEventTriggers(): array
    {
        if (class_exists(\FoF\Byobu\Events\Created::class)) {
            return [
                \FoF\Byobu\Events\Created::class => fn ($event) => $event->actor,
            ];
        }

        return [];
    }

    public function getValue(User $user, array $config = []): int
    {
        $query = $this->db->table('discussions')
            ->where('user_id', $user->id)
            ->where('is_private', 1);

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
        return ['fof/byobu'];
    }
}
