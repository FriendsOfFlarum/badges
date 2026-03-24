import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import type Mithril from 'mithril';
import type { BadgeCategory } from '../../common';
interface CategoryEditModalAttrs extends IFormModalAttrs {
    category?: BadgeCategory;
    onSave?: () => void;
}
export default class CategoryEditModal extends FormModal<CategoryEditModalAttrs> {
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
