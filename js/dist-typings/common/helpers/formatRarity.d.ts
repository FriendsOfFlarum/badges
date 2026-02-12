/**
 * Format rarity percentage for display.
 * Uses 2 decimal places for very rare badges (< 1%),
 * 1 decimal place for others.
 * Shows minimum 0.01% for badges that would round to 0.00%.
 */
export default function formatRarity(earnedCount: number, totalUsers: number): string;
