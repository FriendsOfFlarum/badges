import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { Badge, BadgeCategory } from '../../common';
interface AssignBadgeModalAttrs extends IInternalModalAttrs {
    user: User;
    onAssign?: () => void;
}
export default class AssignBadgeModal extends Modal<AssignBadgeModalAttrs> {
    user: User;
    badges: Badge[];
    categories: BadgeCategory[];
    userBadgeIds: Set<string>;
    loading: boolean;
    saving: boolean;
    selectedBadgeId: string | null;
    reason: string;
    oninit(vnode: Mithril.Vnode<AssignBadgeModalAttrs>): void;
    loadBadges(): Promise<void>;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    assign(): Promise<void>;
}
export {};
