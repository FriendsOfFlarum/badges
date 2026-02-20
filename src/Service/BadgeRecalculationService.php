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

use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\Trigger\TriggerEvaluator;
use FoF\Badges\UserBadge;
use Illuminate\Database\Eloquent\Collection;

class BadgeRecalculationService
{
    protected TriggerEvaluator $evaluator;
    protected BadgeAwarder $awarder;

    public function __construct(TriggerEvaluator $evaluator, BadgeAwarder $awarder)
    {
        $this->evaluator = $evaluator;
        $this->awarder = $awarder;
    }

    /**
     * Get badges with triggers to evaluate.
     *
     * @param int|null $badgeId Optional specific badge ID
     * @param bool $includeManual Include manual badges (for re-apply actions)
     * @return Collection<Badge>
     */
    public function getBadgesToEvaluate(?int $badgeId = null, bool $includeManual = false): Collection
    {
        $query = Badge::query()
            ->where('is_active', true);

        // If a specific badge is requested, don't filter by trigger_config
        // This allows manual badges to be selected for re-apply actions
        if ($badgeId) {
            $query->where('id', $badgeId);
        } elseif (! $includeManual) {
            // Only filter by trigger_config when not including manual badges
            $query->whereNotNull('trigger_config');
        }

        return $query->get();
    }

    /**
     * Preload user badges for efficient lookup.
     *
     * @param array $userIds
     * @param array $badgeIds
     * @return array Map of user_id => [badge_id => UserBadge]
     */
    public function preloadUserBadges(array $userIds, array $badgeIds): array
    {
        if (empty($userIds) || empty($badgeIds)) {
            return [];
        }

        $userBadgesMap = [];

        $userBadges = UserBadge::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('badge_id', $badgeIds)
            ->get();

        foreach ($userBadges as $userBadge) {
            if (! isset($userBadgesMap[$userBadge->user_id])) {
                $userBadgesMap[$userBadge->user_id] = [];
            }
            $userBadgesMap[$userBadge->user_id][$userBadge->badge_id] = $userBadge;
        }

        return $userBadgesMap;
    }

    /**
     * Process a single badge for a user.
     *
     * @param Badge $badge
     * @param User $user
     * @param bool $noRevoke
     * @param array $userBadgesMap Preloaded user badges
     * @param bool $reapplyActions Re-apply actions for existing badge holders
     * @return string 'awarded', 'revoked', 'reapplied', or 'skipped'
     */
    public function processBadgeForUser(
        Badge $badge,
        User $user,
        bool $noRevoke,
        array $userBadgesMap = [],
        bool $reapplyActions = false
    ): string {
        $triggerConfig = $badge->trigger_config;
        $isManualBadge = empty($triggerConfig);

        // Check if user already has this badge
        $userBadge = $userBadgesMap[$user->id][$badge->id] ?? null;
        $hasBadge = $userBadge !== null;

        // If map wasn't provided, check directly
        if (empty($userBadgesMap)) {
            $userBadge = UserBadge::where('user_id', $user->id)
                ->where('badge_id', $badge->id)
                ->first();
            $hasBadge = $userBadge !== null;
        }

        // For manual badges, only re-apply actions if requested and user has the badge
        if ($isManualBadge) {
            if ($hasBadge && $reapplyActions) {
                $this->awarder->executeActionsOnly($user, $badge);

                return 'reapplied';
            }

            return 'skipped';
        }

        // Evaluate trigger conditions for automatic badges
        $qualifies = $this->evaluator->evaluate($user, $badge);

        if ($qualifies && ! $hasBadge) {
            // Award returns [userBadge, wasCreated] to handle race conditions
            [$awardedBadge, $wasCreated] = $this->awarder->award($user, $badge, UserBadge::GRANTED_BY_SYSTEM);

            return $wasCreated ? 'awarded' : 'skipped';
        } elseif ($qualifies && $reapplyActions) {
            // Re-apply actions for existing badge holders
            $this->awarder->executeActionsOnly($user, $badge);

            return 'reapplied';
        } elseif (! $qualifies && $hasBadge && ! $noRevoke && $userBadge) {
            $this->awarder->revoke($userBadge);

            return 'revoked';
        }

        return 'skipped';
    }
}
