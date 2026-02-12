import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type Badge from './Badge';
export default class UserBadge extends Model {
    grantedBy: () => string;
    reason: () => string | null;
    isSeen: () => boolean;
    showOnCard: () => boolean;
    isPrimary: () => boolean;
    earnedAt: () => Date;
    user: () => false | User;
    badge: () => false | Badge;
    grantedByUser: () => false | User;
}
