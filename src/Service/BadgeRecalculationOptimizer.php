<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Service;

use Carbon\Carbon;
use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\Trigger\MetricManager;
use FoF\Badges\UserBadge;
use Illuminate\Database\Eloquent\Builder;

/**
 * Optimizes badge recalculation by skipping unnecessary user evaluations
 * and applying SQL-level pre-filtering.
 */
class BadgeRecalculationOptimizer
{
    public function __construct(protected MetricManager $metricManager)
    {
    }
    /**
     * Metrics that only increase over time (monotonic).
     * Users who already have badges using these metrics with >= or > operators
     * cannot lose them, so they can be skipped during recalculation.
     */
    protected const MONOTONIC_METRICS = [
        'post_count',
        'discussion_count',
        'likes_received',
        'likes_given',
        'member_days',
        'best_answers_received',
        'files_uploaded',
        'polls_created',
        'polls_voted',
        'private_discussions_created',
        'reactions_received',
        'reactions_given',
        'upvotes_received',
        'upvotes_given',
        'downvotes_received',
        'downvotes_given',
    ];

    /**
     * Operators that make a metric condition "monotonic-safe".
     * If a user meets >= X or > X once, they will always meet it
     * for monotonic metrics.
     */
    protected const MONOTONIC_SAFE_OPERATORS = ['>=', '>'];

    /**
     * Mapping of metric types to user table columns for SQL pre-filtering.
     * Only metrics without date_range can be pre-filtered this way.
     */
    protected const METRIC_TO_COLUMN = [
        'post_count' => 'comment_count',
        'discussion_count' => 'discussion_count',
        'has_avatar' => 'avatar_url',
        'has_bio' => 'bio',
        'has_nickname' => 'nickname',
        'best_answers_received' => 'best_answer_count',
        // member_days requires calculation, handled separately
    ];

    /**
     * Check if a badge is "monotonic-safe" - meaning users who already have it
     * cannot lose it, so they can be safely skipped during recalculation.
     *
     * A badge is monotonic-safe when:
     * 1. Logic is AND (all conditions must be met)
     * 2. ALL conditions use monotonic metrics
     * 3. ALL conditions use >= or > operators
     * 4. No date_range is configured (date ranges change the calculation)
     */
    public function isMonotonicSafe(Badge $badge): bool
    {
        $config = $badge->trigger_config;

        if (empty($config) || empty($config['conditions'])) {
            return false;
        }

        // Date ranges change how metrics are calculated, so not monotonic-safe
        if (! empty($config['date_range'])) {
            return false;
        }

        // Tag filtering changes metric calculation
        if (! empty($config['tag_id'])) {
            return false;
        }

        // OR logic means any condition can qualify, not monotonic-safe for skipping
        $logic = strtoupper($config['logic'] ?? 'AND');
        if ($logic !== 'AND') {
            return false;
        }

        // Check all conditions
        foreach ($config['conditions'] as $condition) {
            $metric = $condition['metric'] ?? null;
            $operator = $condition['operator'] ?? '>=';

            // Metric must be available (extension dependencies met)
            if (! $this->metricManager->isAvailable($metric)) {
                return false;
            }

            // Metric must be monotonic
            if (! in_array($metric, self::MONOTONIC_METRICS, true)) {
                return false;
            }

            // Operator must be >= or >
            if (! in_array($operator, self::MONOTONIC_SAFE_OPERATORS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user IDs that can be skipped during recalculation.
     *
     * @param Badge $badge The badge being recalculated
     * @param bool $noRevoke Whether revocation is disabled
     * @param bool $reapplyActions Whether to re-apply actions to existing badge holders
     * @return array User IDs to skip
     */
    public function getUserIdsToSkip(Badge $badge, bool $noRevoke, bool $reapplyActions = false): array
    {
        // If reapplyActions is true, we need to process users who already have the badge
        // so we can re-apply actions to them - don't skip anyone
        if ($reapplyActions) {
            return [];
        }

        // Get users who already have this badge
        $usersWithBadge = UserBadge::where('badge_id', $badge->id)
            ->pluck('user_id')
            ->toArray();

        if (empty($usersWithBadge)) {
            return [];
        }

        // If noRevoke is true, skip ALL users who already have the badge
        // (we won't award again, and we won't revoke)
        if ($noRevoke) {
            return $usersWithBadge;
        }

        // If badge is monotonic-safe, users who have it can't lose it
        if ($this->isMonotonicSafe($badge)) {
            return $usersWithBadge;
        }

        // Otherwise, we can't skip anyone - they might need revocation
        return [];
    }

    /**
     * Build an optimized user query with SQL-level pre-filtering.
     *
     * This applies WHERE clauses based on badge conditions to reduce
     * the number of users that need to be fetched and evaluated.
     *
     * @param Badge $badge The badge to build query for
     * @param array $excludeUserIds User IDs to exclude (already processed or skipped)
     * @param bool $onlyExistingHolders Only query users who already have this badge (for manual badges)
     * @return Builder The optimized query builder
     */
    public function buildOptimizedUserQuery(Badge $badge, array $excludeUserIds = [], bool $onlyExistingHolders = false): Builder
    {
        $query = User::query();

        // For manual badges or re-apply actions mode, only query existing badge holders
        if ($onlyExistingHolders) {
            $userIdsWithBadge = UserBadge::where('badge_id', $badge->id)
                ->pluck('user_id')
                ->toArray();

            if (empty($userIdsWithBadge)) {
                // No users have this badge, return empty query
                $query->whereRaw('1 = 0');

                return $query;
            }

            $query->whereIn('id', $userIdsWithBadge);

            // Still apply exclusions
            if (! empty($excludeUserIds)) {
                $query->whereNotIn('id', $excludeUserIds);
            }

            return $query;
        }

        // Exclude specific users
        if (! empty($excludeUserIds)) {
            $query->whereNotIn('id', $excludeUserIds);
        }

        $config = $badge->trigger_config;

        if (empty($config) || empty($config['conditions'])) {
            return $query;
        }

        // Can't pre-filter if there's a date_range (changes how metrics are calculated)
        if (! empty($config['date_range'])) {
            return $query;
        }

        // Can't pre-filter if there's a tag filter
        if (! empty($config['tag_id'])) {
            return $query;
        }

        $logic = strtoupper($config['logic'] ?? 'AND');

        // For AND logic, we can apply all conditions as WHERE clauses
        // For OR logic, we need to use orWhere groups
        if ($logic === 'AND') {
            foreach ($config['conditions'] as $condition) {
                $this->applyConditionToQuery($query, $condition);
            }
        } else {
            // OR logic: user must match at least one condition
            $query->where(function ($q) use ($config) {
                foreach ($config['conditions'] as $index => $condition) {
                    if ($index === 0) {
                        $this->applyConditionToQuery($q, $condition, false);
                    } else {
                        $q->orWhere(function ($subQ) use ($condition) {
                            $this->applyConditionToQuery($subQ, $condition, false);
                        });
                    }
                }
            });
        }

        return $query;
    }

    /**
     * Apply a single condition to a query builder.
     *
     * @param Builder $query The query builder
     * @param array $condition The condition to apply
     * @param bool $strict If true, applies all filterable conditions
     * @return void
     */
    protected function applyConditionToQuery(Builder $query, array $condition, bool $strict = true): void
    {
        $metric = $condition['metric'] ?? null;
        $operator = $condition['operator'] ?? '>=';
        $value = (int) ($condition['value'] ?? 0);

        if (! $metric) {
            return;
        }

        // Skip if metric's extension dependencies are not available
        if (! $this->metricManager->isAvailable($metric)) {
            return;
        }

        // Handle direct column mappings
        if (isset(self::METRIC_TO_COLUMN[$metric])) {
            $column = self::METRIC_TO_COLUMN[$metric];

            // Special handling for boolean metrics
            if ($metric === 'has_avatar') {
                if ($operator === '>=' && $value >= 1) {
                    $query->whereNotNull($column);
                    $query->where($column, '!=', '');
                } elseif ($operator === '==' && $value === 1) {
                    $query->whereNotNull($column);
                    $query->where($column, '!=', '');
                } elseif ($operator === '==' && $value === 0) {
                    $query->where(function ($q) use ($column) {
                        $q->whereNull($column)->orWhere($column, '=', '');
                    });
                }

                return;
            }

            if ($metric === 'has_bio') {
                if ($operator === '>=' && $value >= 1) {
                    $query->whereNotNull($column);
                    $query->where($column, '!=', '');
                } elseif ($operator === '==' && $value === 1) {
                    $query->whereNotNull($column);
                    $query->where($column, '!=', '');
                } elseif ($operator === '==' && $value === 0) {
                    $query->where(function ($q) use ($column) {
                        $q->whereNull($column)->orWhere($column, '=', '');
                    });
                }

                return;
            }

            if ($metric === 'has_nickname') {
                if ($operator === '>=' && $value >= 1) {
                    $query->whereNotNull($column);
                    $query->where($column, '!=', '');
                } elseif ($operator === '==' && $value === 1) {
                    $query->whereNotNull($column);
                    $query->where($column, '!=', '');
                } elseif ($operator === '==' && $value === 0) {
                    $query->where(function ($q) use ($column) {
                        $q->whereNull($column)->orWhere($column, '=', '');
                    });
                }

                return;
            }

            // Numeric columns
            $sqlOperator = $this->getSqlOperator($operator);
            if ($sqlOperator && $strict) {
                $query->where($column, $sqlOperator, $value);
            }

            return;
        }

        // Handle member_days (requires date calculation)
        if ($metric === 'member_days' && $strict) {
            $this->applyMemberDaysCondition($query, $operator, $value);

            return;
        }

        // likes_received and likes_given require joins, skip for now
        // (they'll be evaluated in PHP)
    }

    /**
     * Apply member_days condition using joined_at column.
     */
    protected function applyMemberDaysCondition(Builder $query, string $operator, int $days): void
    {
        $sqlOperator = $this->getSqlOperator($operator);
        if (! $sqlOperator) {
            return;
        }

        // member_days >= X means user joined at least X days ago
        // joined_at <= NOW() - X days
        $cutoffDate = Carbon::now()->subDays($days);

        // Reverse the operator for date comparison
        // >= X days → joined_at <= cutoff
        // > X days → joined_at < cutoff
        // == X days → complicated, skip for now
        // <= X days → joined_at >= cutoff
        // < X days → joined_at > cutoff

        switch ($operator) {
            case '>=':
                $query->where('joined_at', '<=', $cutoffDate);
                break;
            case '>':
                $query->where('joined_at', '<', $cutoffDate);
                break;
            case '<=':
                $query->where('joined_at', '>=', $cutoffDate);
                break;
            case '<':
                $query->where('joined_at', '>', $cutoffDate);
                break;
                // Skip == and != as they're edge cases
        }
    }

    /**
     * Convert operator string to SQL operator.
     */
    protected function getSqlOperator(string $operator): ?string
    {
        return match ($operator) {
            '>=' => '>=',
            '<=' => '<=',
            '>' => '>',
            '<' => '<',
            '==' => '=',
            '!=' => '!=',
            default => null,
        };
    }

    /**
     * Get optimization statistics for a badge.
     * Useful for debugging and understanding optimization impact.
     */
    public function getOptimizationStats(Badge $badge, bool $noRevoke): array
    {
        $totalUsers = User::count();
        $usersWithBadge = UserBadge::where('badge_id', $badge->id)->count();
        $skippableUsers = count($this->getUserIdsToSkip($badge, $noRevoke));

        $optimizedQuery = $this->buildOptimizedUserQuery($badge, []);
        $preFilteredUsers = $optimizedQuery->count();

        return [
            'total_users' => $totalUsers,
            'users_with_badge' => $usersWithBadge,
            'skippable_users' => $skippableUsers,
            'pre_filtered_users' => $preFilteredUsers,
            'is_monotonic_safe' => $this->isMonotonicSafe($badge),
            'estimated_to_process' => max(0, $preFilteredUsers - $skippableUsers),
        ];
    }

    /**
     * Group badges by their optimization strategy for efficient batch processing.
     *
     * Badges with similar filterable conditions are grouped together so they
     * can share the same optimized user query.
     *
     * @param \Illuminate\Support\Collection $badges Collection of badges to group
     * @param bool $noRevoke Whether revocation is disabled
     * @param bool $reapplyActions Whether to re-apply actions to existing badge holders
     * @return array Array of badge groups, each with 'badges', 'userIds', 'skipUserIds'
     */
    public function groupBadgesByOptimizationStrategy($badges, bool $noRevoke, bool $reapplyActions = false): array
    {
        $groups = [];

        foreach ($badges as $badge) {
            $strategy = $this->getOptimizationStrategy($badge);
            $key = $strategy['key'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'strategy' => $strategy,
                    'badges' => [],
                    'minThresholds' => [],
                ];
            }

            $groups[$key]['badges'][] = $badge;

            // Track minimum thresholds for each metric in this group
            foreach ($strategy['conditions'] as $metric => $condition) {
                if (! isset($groups[$key]['minThresholds'][$metric])) {
                    $groups[$key]['minThresholds'][$metric] = $condition;
                } else {
                    // Keep the LEAST restrictive threshold (minimum value for >= operators)
                    $existing = $groups[$key]['minThresholds'][$metric];
                    if (in_array($condition['operator'], ['>=', '>'], true)) {
                        if ($condition['value'] < $existing['value']) {
                            $groups[$key]['minThresholds'][$metric] = $condition;
                        }
                    }
                }
            }
        }

        // Build optimized user lists for each group
        $result = [];
        foreach ($groups as $key => $group) {
            $groupBadges = $group['badges'];
            $strategy = $group['strategy'];

            // Get users to skip (intersection of all badges' skip lists in this group)
            $skipUserIds = $this->getGroupSkipUserIds($groupBadges, $noRevoke, $reapplyActions);

            // Build optimized query for this group
            $userIds = $this->buildGroupOptimizedQuery(
                $strategy,
                $group['minThresholds'],
                $skipUserIds
            )->pluck('id')->toArray();

            $result[] = [
                'key' => $key,
                'badges' => $groupBadges,
                'badgeIds' => array_map(fn ($b) => $b->id, $groupBadges),
                'userIds' => $userIds,
                'skipUserIds' => $skipUserIds,
                'userCount' => count($userIds),
            ];
        }

        // Sort groups by user count (process smaller groups first for faster feedback)
        usort($result, fn ($a, $b) => $a['userCount'] <=> $b['userCount']);

        return $result;
    }

    /**
     * Get the optimization strategy for a badge.
     *
     * Returns a structured representation of the badge's filterable conditions.
     */
    protected function getOptimizationStrategy(Badge $badge): array
    {
        $config = $badge->trigger_config;

        // Default: no optimization possible
        $strategy = [
            'key' => 'unfilterable',
            'type' => 'unfilterable',
            'conditions' => [],
            'hasDateRange' => false,
            'hasTagFilter' => false,
            'logic' => 'AND',
        ];

        if (empty($config) || empty($config['conditions'])) {
            return $strategy;
        }

        $strategy['hasDateRange'] = ! empty($config['date_range']);
        $strategy['hasTagFilter'] = ! empty($config['tag_id']);
        $strategy['logic'] = strtoupper($config['logic'] ?? 'AND');

        // If has date_range or tag_filter, can't optimize with SQL
        if ($strategy['hasDateRange'] || $strategy['hasTagFilter']) {
            $strategy['key'] = 'unfilterable_'.($strategy['hasDateRange'] ? 'date' : 'tag');

            return $strategy;
        }

        // Extract filterable conditions
        $filterableConditions = [];
        $keyParts = [];

        foreach ($config['conditions'] as $condition) {
            $metric = $condition['metric'] ?? null;
            $operator = $condition['operator'] ?? '>=';
            $value = (int) ($condition['value'] ?? 0);

            if (! $metric) {
                continue;
            }

            // Check if this metric is SQL-filterable and available
            $isFilterable = isset(self::METRIC_TO_COLUMN[$metric]) || $metric === 'member_days';
            $isAvailable = $this->metricManager->isAvailable($metric);

            if ($isFilterable && $isAvailable) {
                $filterableConditions[$metric] = [
                    'metric' => $metric,
                    'operator' => $operator,
                    'value' => $value,
                ];
                // Create key part based on metric and operator (not value, as values can vary)
                $keyParts[] = "{$metric}_{$operator}";
            }
        }

        if (empty($filterableConditions)) {
            $strategy['key'] = 'unfilterable_metrics';

            return $strategy;
        }

        // Sort key parts for consistent grouping
        sort($keyParts);
        $strategy['key'] = $strategy['logic'].'_'.implode('_', $keyParts);
        $strategy['type'] = 'filterable';
        $strategy['conditions'] = $filterableConditions;

        return $strategy;
    }

    /**
     * Get user IDs to skip for a group of badges.
     *
     * For noRevoke mode, we can only skip users who have ALL badges in the group.
     * For monotonic-safe badges, we skip users who have those specific badges.
     */
    protected function getGroupSkipUserIds(array $badges, bool $noRevoke, bool $reapplyActions = false): array
    {
        // If reapplyActions is true, don't skip anyone
        if ($reapplyActions) {
            return [];
        }

        if (empty($badges)) {
            return [];
        }

        // If noRevoke, find users who have ALL badges in this group
        if ($noRevoke) {
            $skipSets = [];
            foreach ($badges as $badge) {
                $skipSets[] = $this->getUserIdsToSkip($badge, true, false);
            }

            // Intersection: users who have ALL badges
            if (count($skipSets) === 1) {
                return $skipSets[0];
            }

            $intersection = $skipSets[0];
            for ($i = 1; $i < count($skipSets); $i++) {
                $intersection = array_intersect($intersection, $skipSets[$i]);
            }

            return array_values($intersection);
        }

        // Without noRevoke, we can only skip users for monotonic-safe badges
        // But since different badges might have different skip requirements,
        // we can't safely skip anyone in a mixed group
        return [];
    }

    /**
     * Build optimized query for a group of badges using minimum thresholds.
     */
    protected function buildGroupOptimizedQuery(
        array $strategy,
        array $minThresholds,
        array $excludeUserIds
    ): Builder {
        $query = User::query();

        if (! empty($excludeUserIds)) {
            $query->whereNotIn('id', $excludeUserIds);
        }

        // If unfilterable, return all users
        if ($strategy['type'] !== 'filterable') {
            return $query;
        }

        $logic = $strategy['logic'];

        // Apply minimum thresholds as SQL conditions
        if ($logic === 'AND') {
            foreach ($minThresholds as $metric => $condition) {
                $this->applyConditionToQuery($query, $condition);
            }
        } else {
            // OR logic: any condition can match
            $query->where(function ($q) use ($minThresholds) {
                $first = true;
                foreach ($minThresholds as $metric => $condition) {
                    if ($first) {
                        $this->applyConditionToQuery($q, $condition, false);
                        $first = false;
                    } else {
                        $q->orWhere(function ($subQ) use ($condition) {
                            $this->applyConditionToQuery($subQ, $condition, false);
                        });
                    }
                }
            });
        }

        return $query;
    }

    /**
     * Get summary statistics for grouped badges.
     */
    public function getGroupedOptimizationStats(\Illuminate\Support\Collection $badges, bool $noRevoke): array
    {
        $groups = $this->groupBadgesByOptimizationStrategy($badges, $noRevoke);
        $totalUsers = User::count();

        $stats = [
            'total_users' => $totalUsers,
            'total_badges' => count($badges),
            'group_count' => count($groups),
            'groups' => [],
            'total_user_evaluations' => 0,
            'naive_evaluations' => $totalUsers * count($badges),
        ];

        foreach ($groups as $group) {
            $groupStats = [
                'key' => $group['key'],
                'badge_count' => count($group['badges']),
                'badge_names' => array_map(fn ($b) => $b->name, $group['badges']),
                'user_count' => $group['userCount'],
                'skip_count' => count($group['skipUserIds']),
            ];
            $stats['groups'][] = $groupStats;
            $stats['total_user_evaluations'] += $group['userCount'] * count($group['badges']);
        }

        $stats['optimization_ratio'] = $stats['naive_evaluations'] > 0
            ? round((1 - $stats['total_user_evaluations'] / $stats['naive_evaluations']) * 100, 1)
            : 0;

        return $stats;
    }
}
