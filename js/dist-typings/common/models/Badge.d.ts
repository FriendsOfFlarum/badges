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
    name: () => string;
    slug: () => string;
    description: () => string | null;
    icon: () => string;
    iconColor: () => string;
    backgroundColor: () => string;
    triggerConfig: () => TriggerConfig | null;
    actions: () => BadgeActions | null;
    isActive: () => boolean;
    isVisible: () => boolean;
    earnedCount: () => number;
    order: () => number;
    createdAt: () => Date;
    rarity: () => number;
    canEdit: () => boolean;
    categoryId: () => number | null;
    category: () => false | BadgeCategory;
}
