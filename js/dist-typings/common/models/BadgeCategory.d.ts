import Model from 'flarum/common/Model';
import type Badge from './Badge';
export default class BadgeCategory extends Model {
    name: () => string;
    slug: () => string;
    description: () => string | null;
    isEnabled: () => boolean;
    order: () => number;
    createdAt: () => Date;
    badgeCount: () => number;
    badges: () => false | (Badge | undefined)[];
}
