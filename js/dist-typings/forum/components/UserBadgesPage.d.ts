import UserPage from 'flarum/forum/components/UserPage';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { UserBadge } from '../../common';
export default class UserBadgesPage extends UserPage {
    loading: boolean;
    loadingMore: boolean;
    userBadges: UserBadge[];
    totalBadgeCount: number;
    hasMoreBadges: boolean;
    oninit(vnode: Mithril.Vnode): void;
    show(user: User): void;
    loadUserBadges(loadMore?: boolean): Promise<void>;
    content(): Mithril.Children;
    renderRecalculateButton(): Mithril.Children;
    showAssignModal(): void;
    showRecalculateModal(): void;
    showBadgeModal(badge: any): void;
    /**
     * Check if badge was earned within the last 7 days
     */
    isRecentBadge(earnedAt: Date | string | null): boolean;
    toggleFavorite(userBadge: UserBadge): Promise<void>;
    toggleVisibility(userBadge: UserBadge): Promise<void>;
    revokeBadge(userBadge: UserBadge, badgeName: string): Promise<void>;
}
