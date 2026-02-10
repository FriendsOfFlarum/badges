/**
 * Format rarity percentage for display.
 * Uses 2 decimal places for very rare badges (< 1%),
 * 1 decimal place for others.
 * Shows minimum 0.01% for badges that would round to 0.00%.
 */
export default function formatRarity(earnedCount: number, totalUsers: number): string {
  if (totalUsers <= 0 || earnedCount <= 0) return '0.00';

  const rarity = (earnedCount / totalUsers) * 100;

  // For very rare badges (< 1%), show 2 decimal places with minimum 0.01%
  if (rarity < 1) {
    const formatted = rarity.toFixed(2);
    // If it rounds to 0.00, show 0.01 as minimum
    return formatted === '0.00' ? '0.01' : formatted;
  }

  // For others, show 1 decimal place
  return rarity.toFixed(1);
}
