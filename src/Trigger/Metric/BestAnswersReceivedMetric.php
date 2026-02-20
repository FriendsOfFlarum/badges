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

class BestAnswersReceivedMetric implements MetricInterface
{
    protected ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function getType(): string
    {
        return 'best_answers_received';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.best_answers_received';
    }

    public function getEventTriggers(): array
    {
        // BestAnswerSet event - user extractor gets the post author (who received the best answer)
        if (class_exists(\FoF\BestAnswer\Events\BestAnswerSet::class)) {
            return [
                \FoF\BestAnswer\Events\BestAnswerSet::class => fn ($event) => $event->post->user,
            ];
        }

        return [];
    }

    public function getValue(User $user, array $config = []): int
    {
        // Always query database directly to avoid stale in-memory model values
        // This is important because event listener order may cause $user->best_answer_count
        // to be outdated when BestAnswerSet event triggers badge evaluation
        $query = $this->db->table('discussions')
            ->join('posts', 'discussions.best_answer_post_id', '=', 'posts.id')
            ->where('posts.user_id', $user->id)
            ->whereNotNull('discussions.best_answer_post_id');

        // Apply date range filter if present
        if (isset($config['date_range'])) {
            if (isset($config['date_range']['start']) && $config['date_range']['start']) {
                $query->where('discussions.best_answer_set_at', '>=', Carbon::parse($config['date_range']['start']));
            }

            if (isset($config['date_range']['end']) && $config['date_range']['end']) {
                $query->where('discussions.best_answer_set_at', '<=', Carbon::parse($config['date_range']['end']));
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
        return ['fof/best-answer'];
    }
}
