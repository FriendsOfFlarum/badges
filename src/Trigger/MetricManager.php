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

use Flarum\Extension\ExtensionManager;

class MetricManager
{
    /**
     * Registered metrics keyed by type.
     *
     * @var array<string, MetricInterface>
     */
    protected array $metrics = [];

    /**
     * Cache of event class to metric types mapping.
     *
     * @var array<string, array<string>>|null
     */
    protected ?array $eventCache = null;

    public function __construct(protected ExtensionManager $extensions)
    {
    }

    /**
     * Register a metric.
     */
    public function register(MetricInterface $metric): void
    {
        $this->metrics[$metric->getType()] = $metric;
        $this->eventCache = null; // Invalidate cache
    }

    /**
     * Get a metric by type.
     */
    public function get(string $type): ?MetricInterface
    {
        return $this->metrics[$type] ?? null;
    }

    /**
     * Check if a metric is available (all extension dependencies are met).
     */
    public function isAvailable(string $type): bool
    {
        $metric = $this->get($type);

        if (! $metric) {
            return false;
        }

        foreach ($metric->getExtensionDependencies() as $extensionId) {
            // Convert package name to extension ID format (e.g., "fof/user-bio" -> "fof-user-bio")
            $normalizedId = str_replace('/', '-', $extensionId);

            if (! $this->extensions->isEnabled($normalizedId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all registered metrics.
     *
     * @return array<string, MetricInterface>
     */
    public function all(): array
    {
        return $this->metrics;
    }

    /**
     * Get metric types that are triggered by a specific event.
     *
     * @param string $eventClass The fully qualified event class name
     * @return array<string> Array of metric type strings
     */
    public function getTypesForEvent(string $eventClass): array
    {
        if ($this->eventCache === null) {
            $this->buildEventCache();
        }

        return $this->eventCache[$eventClass] ?? [];
    }

    /**
     * Build the event to metric types cache.
     */
    protected function buildEventCache(): void
    {
        $this->eventCache = [];

        foreach ($this->metrics as $type => $metric) {
            foreach ($metric->getEventTriggers() as $eventClass => $extractor) {
                if (! isset($this->eventCache[$eventClass])) {
                    $this->eventCache[$eventClass] = [];
                }
                $this->eventCache[$eventClass][] = $type;
            }
        }
    }

    /**
     * Get the user extractor callable for a metric and event.
     *
     * @param string $type The metric type
     * @param string $eventClass The event class
     * @return callable|null
     */
    public function getUserExtractor(string $type, string $eventClass): ?callable
    {
        $metric = $this->get($type);

        if (! $metric) {
            return null;
        }

        $triggers = $metric->getEventTriggers();

        return $triggers[$eventClass] ?? null;
    }
}
