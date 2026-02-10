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
 */
export default function getRarityInfo(earnedCount: number | undefined, totalUsers: number | undefined): RarityInfo {
  if (!earnedCount || !totalUsers || totalUsers <= 0 || earnedCount <= 0) {
    return {
      tier: 'common',
      label: app.translator.trans('fof-badges.lib.rarity_common') as string,
      color: '#95a5a6',
      percent: 0,
    };
  }

  const percent = Math.min(100, (earnedCount / totalUsers) * 100);

  // Legendary: < 1%
  if (percent < 1) {
    return {
      tier: 'legendary',
      label: app.translator.trans('fof-badges.lib.rarity_legendary') as string,
      color: '#e74c3c',
      percent,
    };
  }

  // Epic: 1-5%
  if (percent <= 5) {
    return {
      tier: 'epic',
      label: app.translator.trans('fof-badges.lib.rarity_epic') as string,
      color: '#9b59b6',
      percent,
    };
  }

  // Rare: 5-20%
  if (percent <= 20) {
    return {
      tier: 'rare',
      label: app.translator.trans('fof-badges.lib.rarity_rare') as string,
      color: '#3498db',
      percent,
    };
  }

  // Uncommon: 20-50%
  if (percent <= 50) {
    return {
      tier: 'uncommon',
      label: app.translator.trans('fof-badges.lib.rarity_uncommon') as string,
      color: '#2ecc71',
      percent,
    };
  }

  // Common: > 50%
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
