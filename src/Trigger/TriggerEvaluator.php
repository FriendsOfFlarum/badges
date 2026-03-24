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

use Carbon\Carbon;
use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\UserBadge;

class TriggerEvaluator
{
    public function __construct(protected MetricManager $metricManager)
    {
    }

    /**
     * Evaluate if a user qualifies for a badge.
     *
     * @param User $user The user to evaluate
     * @param Badge $badge The badge to check
     * @return bool True if user qualifies
     */
    public function evaluate(User $user, Badge $badge): bool
    {
        $config = $badge->trigger_config;

        // Manual badges (no trigger config) always return false
        if (empty($config)) {
            return false;
        }

        // Check date range if present
        if (! $this->checkDateRange($config)) {
            return false;
        }

        // Evaluate conditions
        return $this->evaluateConditions($user, $config);
    }

    /**
     * Find badges a user qualifies for based on an event.
     *
     * @param User $user The user to evaluate
     * @param string $eventClass The event class that triggered evaluation
     * @return array<Badge> Badges the user now qualifies for
     */
    public function evaluateForEvent(User $user, string $eventClass): array
    {
        // Get metric types triggered by this event
        $metricTypes = $this->metricManager->getTypesForEvent($eventClass);

        if (empty($metricTypes)) {
            return [];
        }

        // Get active badges that use these metrics
        $badges = $this->getBadgesUsingMetrics($metricTypes);

        // Filter out badges user already has
        $userBadgeIds = UserBadge::where('user_id', $user->id)
            ->pluck('badge_id')
            ->toArray();

        $candidates = $badges->filter(function (Badge $badge) use ($userBadgeIds) {
            return ! in_array($badge->id, $userBadgeIds);
        });

        // Evaluate each badge
        $qualifiedBadges = [];

        /** @var Badge $badge */
        foreach ($candidates as $badge) {
            if ($this->evaluate($user, $badge)) {
                $qualifiedBadges[] = $badge;
            }
        }

        return $qualifiedBadges;
    }

    /**
     * Check if current time is within the badge's date range.
     *
     * @param array $config The trigger config
     * @return bool True if within range or no range specified
     */
    protected function checkDateRange(array $config): bool
    {
        if (! isset($config['date_range'])) {
            return true;
        }

        $dateRange = $config['date_range'];
        $now = Carbon::now();

        if (isset($dateRange['start'])) {
            $start = Carbon::parse($dateRange['start']);
            if ($now->lt($start)) {
                return false;
            }
        }

        if (isset($dateRange['end'])) {
            $end = Carbon::parse($dateRange['end']);
            if ($now->gt($end)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate all conditions in the trigger config.
     *
     * @param User $user The user to evaluate
     * @param array $config The trigger config
     * @return bool True if conditions are met
     */
    protected function evaluateConditions(User $user, array $config): bool
    {
        $conditions = $config['conditions'] ?? [];

        if (empty($conditions)) {
            return false;
        }

        $logic = strtoupper($config['logic'] ?? 'AND');
        $results = [];

        foreach ($conditions as $condition) {
            $results[] = $this->evaluateCondition($user, $condition, $config);
        }

        if ($logic === 'OR') {
            return in_array(true, $results, true);
        }

        // AND logic (default)
        return ! in_array(false, $results, true);
    }

    /**
     * Evaluate a single condition.
     *
     * @param User $user The user to evaluate
     * @param array $condition The condition to check
     * @param array $config The full trigger config (for date_range, tag_id, etc.)
     * @return bool True if condition is met
     */
    protected function evaluateCondition(User $user, array $condition, array $config): bool
    {
        $metricType = $condition['metric'] ?? null;
        $operator = $condition['operator'] ?? '>=';
        $targetValue = $condition['value'] ?? 0;

        if (! $metricType) {
            return false;
        }

        // Check if metric's extension dependencies are available
        if (! $this->metricManager->isAvailable($metricType)) {
            return false;
        }

        $metric = $this->metricManager->get($metricType);

        if (! $metric) {
            return false;
        }

        // Get actual value from metric, passing full config for date_range, tag_id, etc.
        $actualValue = $metric->getValue($user, $config);

        return $this->compare($actualValue, $operator, $targetValue);
    }

    /**
     * Compare two values using an operator.
     *
     * @param int $actual The actual value
     * @param string $operator The comparison operator
     * @param int $target The target value
     * @return bool Result of comparison
     */
    protected function compare(int $actual, string $operator, int $target): bool
    {
        return match ($operator) {
            '>=' => $actual >= $target,
            '<=' => $actual <= $target,
            '==' => $actual === $target,
            '>' => $actual > $target,
            '<' => $actual < $target,
            '!=' => $actual !== $target,
            default => false,
        };
    }

    /**
     * Get active badges that use any of the given metric types.
     *
     * @param array<string> $metricTypes
     * @return \Illuminate\Support\Collection<int, Badge>
     */
    protected function getBadgesUsingMetrics(array $metricTypes): \Illuminate\Support\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Badge> $badges */
        $badges = Badge::where('is_active', true)
            ->whereNotNull('trigger_config')
            ->get();

        return $badges->filter(function (Badge $badge) use ($metricTypes) {
            $config = $badge->trigger_config;
            $conditions = $config['conditions'] ?? [];

            foreach ($conditions as $condition) {
                if (in_array($condition['metric'] ?? null, $metricTypes)) {
                    return true;
                }
            }

            return false;
        });
    }
}
