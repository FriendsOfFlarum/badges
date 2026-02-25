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
export default function getRarityInfo(earnedCount: number | undefined, totalUsers: number | undefined): RarityInfo;
/**
 * Format percentage for display.
 * Uses appropriate decimal places based on value.
 */
export declare function formatPercent(percent: number): string;
