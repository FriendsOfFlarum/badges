import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type { BadgeCategory } from '../../common';
interface CategoryEditModalAttrs extends IInternalModalAttrs {
    category?: BadgeCategory;
    onSave?: () => void;
}
export default class CategoryEditModal extends Modal<CategoryEditModalAttrs> {
    category: BadgeCategory | null;
    isNew: boolean;
    name: string;
    slug: string;
    description: string;
    isEnabled: boolean;
    loading: boolean;
    oninit(vnode: Mithril.Vnode<CategoryEditModalAttrs>): void;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    onsubmit(e: SubmitEvent): void;
    save(): Promise<void>;
}
export {};
