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

use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;

class HasNicknameMetric implements MetricInterface
{
    public function getType(): string
    {
        return 'has_nickname';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.has_nickname';
    }

    public function getEventTriggers(): array
    {
        return [
            'user_nickname_changed' => fn ($user) => $user,
        ];
    }

    public function getValue(User $user, array $config = []): int
    {
        // flarum/nicknames stores nickname in the 'nickname' attribute
        return ! empty($user->getAttribute('nickname')) ? 1 : 0;
    }

    public function isBoolean(): bool
    {
        return true;
    }

    public function getConfigFields(): array
    {
        return [];
    }

    public function getExtensionDependencies(): array
    {
        return ['flarum/nicknames'];
    }
}
