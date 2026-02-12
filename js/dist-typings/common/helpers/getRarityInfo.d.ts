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
export default function getRarityInfo(earnedCount: number | undefined, totalUsers: number | undefined): RarityInfo;
/**
 * Format percentage for display.
 * Uses appropriate decimal places based on value.
 */
export declare function formatPercent(percent: number): string;
