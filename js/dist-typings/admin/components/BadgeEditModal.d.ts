import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type { Badge, BadgeCategory, TriggerConfig, BadgeActions } from '../../common';
interface BadgeEditModalAttrs extends IInternalModalAttrs {
    badge?: Badge;
    categories: BadgeCategory[];
    onSave?: () => void;
}
export default class BadgeEditModal extends Modal<BadgeEditModalAttrs> {
    badge: Badge | null;
    isNew: boolean;
    name: string;
    slug: string;
    description: string;
    icon: string;
    iconColor: string;
    backgroundColor: string;
    categoryId: string | null;
    isActive: boolean;
    isVisible: boolean;
    triggerConfig: TriggerConfig | null;
    actions: BadgeActions;
    loading: boolean;
    oninit(vnode: Mithril.Vnode<BadgeEditModalAttrs>): void;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    onsubmit(e: SubmitEvent): void;
    save(): Promise<void>;
    /**
     * Check if the form can be saved
     */
    canSave(): boolean;
    /**
     * Get validation error message if any
     */
    getValidationError(): string | null;
    /**
     * Check if trigger config contains tag_posts metric
     */
    hasTagMetric(): boolean;
}
export {};
