import app from 'flarum/common/app';

export interface RarityInfo {
  tier: string;
  label: string;
  color: string;
  percent: number;
}

/**
 * Get rarity information based on earned count and total users.
 * Returns tier key, translated label, color, and percentage.
 *
 * Thresholds use whichever is the higher bar — the percentage threshold
 * or a minimum user count — so small communities get meaningful tiers.
 *
 * Legendary : earnedCount < max(1%  of totalUsers, 2)
 * Epic       : earnedCount < max(5%  of totalUsers, 3)
 * Rare       : earnedCount < max(20% of totalUsers, 5)
 * Uncommon   : earnedCount < 50% of totalUsers
 * Common     : earnedCount >= 50% of totalUsers
 */
export default function getRarityInfo(earnedCount: number | undefined, totalUsers: number | undefined): RarityInfo {
  if (!earnedCount || !totalUsers || totalUsers <= 0 || earnedCount <= 0) {
    return {
      tier: 'legendary',
      label: app.translator.trans('fof-badges.lib.rarity_legendary') as string,
      color: '#e74c3c',
      percent: 0,
    };
  }

  const percent = Math.min(100, (earnedCount / totalUsers) * 100);

  const legendaryThreshold = Math.max(totalUsers * 0.01, 2);
  const epicThreshold = Math.max(totalUsers * 0.05, 3);
  const rareThreshold = Math.max(totalUsers * 0.2, 5);
  const uncommonThreshold = totalUsers * 0.5;

  // Legendary: below legendary threshold
  if (earnedCount < legendaryThreshold) {
    return {
      tier: 'legendary',
      label: app.translator.trans('fof-badges.lib.rarity_legendary') as string,
      color: '#e74c3c',
      percent,
    };
  }

  // Epic: below epic threshold
  if (earnedCount < epicThreshold) {
    return {
      tier: 'epic',
      label: app.translator.trans('fof-badges.lib.rarity_epic') as string,
      color: '#9b59b6',
      percent,
    };
  }

  // Rare: below rare threshold
  if (earnedCount < rareThreshold) {
    return {
      tier: 'rare',
      label: app.translator.trans('fof-badges.lib.rarity_rare') as string,
      color: '#3498db',
      percent,
    };
  }

  // Uncommon: below 50%
  if (earnedCount < uncommonThreshold) {
    return {
      tier: 'uncommon',
      label: app.translator.trans('fof-badges.lib.rarity_uncommon') as string,
      color: '#2ecc71',
      percent,
    };
  }

  // Common: 50%+
  return {
    tier: 'common',
    label: app.translator.trans('fof-badges.lib.rarity_common') as string,
    color: '#95a5a6',
    percent,
  };
}

/**
 * Format percentage for display.
 * Uses appropriate decimal places based on value.
 */
export function formatPercent(percent: number): string {
  const clamped = Math.min(100, Math.max(0, percent));
  if (clamped <= 0) return '0';
  if (clamped < 1) {
    const formatted = clamped.toFixed(2);
    return formatted === '0.00' ? '0.01' : formatted;
  }
  if (clamped < 10) return clamped.toFixed(1);
  return Math.round(clamped).toString();
}
