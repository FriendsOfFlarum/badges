import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { Badge } from '../../common';
interface BadgeCardAttrs {
    badge: Badge;
    onclick?: () => void;
    earnedAt?: Date | string | null;
    isFavorite?: boolean;
    isHidden?: boolean;
    isNew?: boolean;
    isManual?: boolean;
    isOwner?: boolean;
    isOwned?: boolean;
    reason?: string | null;
}
export default class BadgeCard extends Component<BadgeCardAttrs> {
    view(): Mithril.Children;
}
export {};
