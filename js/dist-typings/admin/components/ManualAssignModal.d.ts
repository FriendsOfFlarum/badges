import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { Badge, UserBadge } from '../../common';
interface ManualAssignModalAttrs extends IInternalModalAttrs {
    badge: Badge;
    onAssign?: () => void;
}
export default class ManualAssignModal extends Modal<ManualAssignModalAttrs> {
    badge: Badge;
    activeTab: 'assign' | 'holders';
    searchQuery: string;
    searchResults: User[];
    selectedUser: User | null;
    reason: string;
    loading: boolean;
    searching: boolean;
    searchTimeout: ReturnType<typeof setTimeout> | null;
    holders: UserBadge[];
    holdersLoading: boolean;
    holdersSearch: string;
    holdersSearchTimeout: ReturnType<typeof setTimeout> | null;
    holdersOffset: number;
    holdersLimit: number;
    holdersTotal: number;
    revokingId: string | null;
    oninit(vnode: Mithril.Vnode<ManualAssignModalAttrs>): void;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    renderAssignTab(): Mithril.Children;
    renderHoldersTab(): Mithril.Children;
    switchTab(tab: 'assign' | 'holders'): void;
    formatDate(date: Date | null): string;
    onsubmit(e: SubmitEvent): void;
    onSearchInput(query: string): void;
    search(query: string): Promise<void>;
    selectUser(user: User): void;
    assign(): Promise<void>;
    onHoldersSearchInput(query: string): void;
    loadHoldersPage(offset: number): void;
    loadHolders(): Promise<void>;
    revokeFromUser(userBadge: UserBadge, userName: string): Promise<void>;
}
export {};
