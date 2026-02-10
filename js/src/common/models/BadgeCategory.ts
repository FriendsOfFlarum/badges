import Model from 'flarum/common/Model';
import type Badge from './Badge';

export default class BadgeCategory extends Model {
  // Attributes
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  description = Model.attribute<string | null>('description');
  isEnabled = Model.attribute<boolean>('isEnabled');
  order = Model.attribute<number>('order');
  createdAt = Model.attribute<Date, string>('createdAt', Model.transformDate);
  badgeCount = Model.attribute<number>('badgeCount');

  // Relationships
  badges = Model.hasMany<Badge>('badges');
}
