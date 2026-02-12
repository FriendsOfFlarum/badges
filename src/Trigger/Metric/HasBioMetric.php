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

use Flarum\User\User;
use FoF\Badges\Trigger\MetricInterface;

class HasBioMetric implements MetricInterface
{
    public function getType(): string
    {
        return 'has_bio';
    }

    public function getTranslationKey(): string
    {
        return 'fof-badges.admin.metrics.has_bio';
    }

    public function getEventTriggers(): array
    {
        return [
            // user_bio_changed is triggered by Model::afterSave in extend.php
            'user_bio_changed' => fn ($user) => $user,
        ];
    }

    public function getValue(User $user, array $config = []): int
    {
        return ! empty($user->getAttribute('bio')) ? 1 : 0;
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
        return ['fof/user-bio'];
    }
}
