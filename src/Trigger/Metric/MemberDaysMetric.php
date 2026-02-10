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
use Flarum\Discussion\Event\Started;
use Flarum\Post\Event\Posted;
use Flarum\User\Event\LoggedIn;
use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;

class MemberDaysMetric implements MetricInterface
{
    public function getType(): string
    {
        return 'member_days';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.member_days';
    }

    public function getEventTriggers(): array
    {
        // Trigger on multiple events so time-based badges get evaluated
        // during regular user activity
        $triggers = [
            LoggedIn::class => fn ($event) => $event->user,
            Posted::class => fn ($event) => $event->post->user,
            Started::class => fn ($event) => $event->actor,
        ];

        // Also trigger on likes if flarum/likes is enabled
        if (class_exists(\Flarum\Likes\Event\PostWasLiked::class)) {
            $triggers[\Flarum\Likes\Event\PostWasLiked::class] = fn ($event) => $event->post->user;
        }

        return $triggers;
    }

    public function getValue(User $user, array $config = []): int
    {
        if (! $user->joined_at) {
            return 0;
        }

        $joinedAt = Carbon::parse($user->joined_at);
        $now = Carbon::now();

        return (int) $joinedAt->diffInDays($now);
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
