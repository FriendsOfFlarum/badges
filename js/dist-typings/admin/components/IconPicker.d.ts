import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
interface IconPickerAttrs {
    value: string;
    onchange: (icon: string) => void;
}
export default class IconPicker extends Component<IconPickerAttrs> {
    expanded: boolean;
    customIcon: string;
    oninit(vnode: Mithril.Vnode<IconPickerAttrs>): void;
    view(): Mithril.Children;
    selectIcon(icon: string): void;
}
export {};
