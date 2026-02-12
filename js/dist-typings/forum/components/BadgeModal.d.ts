import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type { Badge, UserBadge } from '../../common';
interface BadgeModalAttrs extends IInternalModalAttrs {
    badge: Badge;
    onhide?: () => void;
}
export default class BadgeModal extends Modal<BadgeModalAttrs> {
    badge: Badge;
    loading: boolean;
    earnedUsers: UserBadge[];
    oninit(vnode: Mithril.Vnode<BadgeModalAttrs>): void;
    loadEarnedUsers(): Promise<void>;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    onremove(vnode: Mithril.VnodeDOM<BadgeModalAttrs>): void;
}
export {};
