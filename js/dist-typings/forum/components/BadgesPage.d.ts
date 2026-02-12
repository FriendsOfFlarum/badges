import Page from 'flarum/common/components/Page';
import type Mithril from 'mithril';
import type { Badge, BadgeCategory } from '../../common';
export default class BadgesPage extends Page {
    loading: boolean;
    badges: Badge[];
    categories: BadgeCategory[];
    ownedBadgeIds: Set<string>;
    oninit(vnode: Mithril.Vnode): void;
    oncreate(vnode: Mithril.VnodeDOM): void;
    loadData(): Promise<void>;
    openBadgeById(id: string): void;
    view(): Mithril.Children;
    renderContent(): Mithril.Children;
    renderBadgesList(): Mithril.Children;
    showBadgeModal(badge: Badge): void;
}
