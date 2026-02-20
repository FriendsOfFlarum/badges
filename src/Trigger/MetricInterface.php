<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Trigger;

use Flarum\User\User;

interface MetricInterface
{
    /**
     * Get the unique type identifier for this metric.
     *
     * @return string e.g., 'post_count', 'likes_received'
     */
    public function getType(): string;

    /**
     * Get the translation key for the admin UI label.
     *
     * @return string e.g., 'fof-badges.admin.metrics.post_count'
     */
    public function getTranslationKey(): string;

    /**
     * Get the events that trigger this metric to be evaluated.
     *
     * Returns a map of Event class => callable that extracts the User from the event.
     *
     * @return array<string, callable> e.g., [Posted::class => fn($event) => $event->actor]
     */
    public function getEventTriggers(): array;

    /**
     * Get the current value of this metric for a user.
     *
     * @param User $user The user to evaluate
     * @param array $config Additional config from trigger_config (e.g., date_range, tag_id)
     * @return int The metric value
     */
    public function getValue(User $user, array $config = []): int;

    /**
     * Whether this metric is a boolean (0/1) value.
     *
     * @return bool True for metrics like has_avatar, has_bio
     */
    public function isBoolean(): bool;

    /**
     * Get additional config fields needed for this metric.
     *
     * @return array e.g., ['tag_id' => 'number'] for tag_posts metric
     */
    public function getConfigFields(): array;

    /**
     * Get required extension dependencies for this metric.
     *
     * @return array e.g., ['flarum/likes'] for likes_received metric
     */
    public function getExtensionDependencies(): array;
}
