import Extend from 'flarum/common/extenders';
import Badge from './models/Badge';
import BadgeCategory from './models/BadgeCategory';
import UserBadge from './models/UserBadge';

export default [new Extend.Store().add('badges', Badge).add('badge-categories', BadgeCategory).add('user-badges', UserBadge)];
