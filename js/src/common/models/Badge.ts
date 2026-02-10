import Model from 'flarum/common/Model';
import type BadgeCategory from './BadgeCategory';

export interface TriggerConfig {
  conditions: Condition[];
  logic: 'AND' | 'OR';
  date_range?: {
    start: string;
    end: string;
  };
  tag_id?: number;
}

export interface Condition {
  metric: string;
  operator: '>=' | '<=' | '==' | '>' | '<' | '!=';
  value: number;
}

export interface BadgeActions {
  send_notification?: boolean;
  add_to_group?: number | null;
}

export default class Badge extends Model {
  // Attributes
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  description = Model.attribute<string | null>('description');
  icon = Model.attribute<string>('icon');
  iconColor = Model.attribute<string>('iconColor');
  backgroundColor = Model.attribute<string>('backgroundColor');
  triggerConfig = Model.attribute<TriggerConfig | null>('triggerConfig');
  actions = Model.attribute<BadgeActions | null>('actions');
  isActive = Model.attribute<boolean>('isActive');
  isVisible = Model.attribute<boolean>('isVisible');
  earnedCount = Model.attribute<number>('earnedCount');
  order = Model.attribute<number>('order');
  createdAt = Model.attribute<Date, string>('createdAt', Model.transformDate);
  rarity = Model.attribute<number>('rarity');
  canEdit = Model.attribute<boolean>('canEdit');
  categoryId = Model.attribute<number | null>('categoryId');

  // Relationships
  category = Model.hasOne<BadgeCategory>('category');
}
