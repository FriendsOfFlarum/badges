import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { Badge } from '../../common';
interface UserRecalculateModalAttrs extends IInternalModalAttrs {
    user: User;
    onRecalculate?: () => void;
}
export default class UserRecalculateModal extends Modal<UserRecalculateModalAttrs> {
    loading: boolean;
    recalculating: boolean;
    badges: Badge[];
    selectedBadgeId: string;
    noRevoke: boolean;
    reapplyActions: boolean;
    oninit(vnode: Mithril.Vnode<UserRecalculateModalAttrs>): void;
    className(): string;
    title(): Mithril.Children;
    loadBadges(): Promise<void>;
    content(): Mithril.Children;
    submit(): Promise<void>;
}
export {};
