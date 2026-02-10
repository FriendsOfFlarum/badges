import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type Badge from './Badge';

export default class UserBadge extends Model {
  // Attributes
  grantedBy = Model.attribute<string>('grantedBy');
  reason = Model.attribute<string | null>('reason');
  isSeen = Model.attribute<boolean>('isSeen');
  showOnCard = Model.attribute<boolean>('showOnCard');
  isPrimary = Model.attribute<boolean>('isPrimary');
  earnedAt = Model.attribute<Date, string>('earnedAt', Model.transformDate);

  // Relationships
  user = Model.hasOne<User>('user');
  badge = Model.hasOne<Badge>('badge');
  grantedByUser = Model.hasOne<User>('grantedByUser');
}
