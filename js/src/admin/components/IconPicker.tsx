import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

interface IconPickerAttrs {
  value: string;
  onchange: (icon: string) => void;
}

// Common Font Awesome 5 icons for badges
const COMMON_ICONS = [
  // Awards & Achievements
  'fas fa-award',
  'fas fa-medal',
  'fas fa-trophy',
  'fas fa-star',
  'fas fa-crown',
  'fas fa-gem',
  'fas fa-certificate',
  'fas fa-ribbon',

  // Status & Actions
  'fas fa-shield-alt',
  'fas fa-fire',
  'fas fa-bolt',
  'fas fa-heart',
  'fas fa-thumbs-up',
  'fas fa-check',
  'fas fa-check-circle',
  'fas fa-flag',

  // Communication
  'fas fa-comment',
  'fas fa-comments',
  'fas fa-pen',
  'fas fa-pencil-alt',
  'fas fa-book',
  'fas fa-bookmark',
  'fas fa-newspaper',
  'fas fa-quote-left',

  // People & Community
  'fas fa-user',
  'fas fa-users',
  'fas fa-user-plus',
  'fas fa-handshake',
  'fas fa-hand-holding-heart',
  'fas fa-hands-helping',
  'fas fa-user-shield',
  'fas fa-user-graduate',

  // Objects & Symbols
  'fas fa-gift',
  'fas fa-birthday-cake',
  'fas fa-calendar',
  'fas fa-clock',
  'fas fa-graduation-cap',
  'fas fa-lightbulb',
  'fas fa-rocket',
  'fas fa-magic',

  // Nature & Weather
  'fas fa-sun',
  'fas fa-moon',
  'fas fa-snowflake',
  'fas fa-leaf',
  'fas fa-tree',
  'fas fa-seedling',
  'fas fa-paw',
  'fas fa-dove',

  // Tech & Gaming
  'fas fa-gamepad',
  'fas fa-puzzle-piece',
  'fas fa-code',
  'fas fa-terminal',
  'fas fa-bug',
  'fas fa-wrench',
  'fas fa-cog',
  'fas fa-database',
];

export default class IconPicker extends Component<IconPickerAttrs> {
  expanded: boolean = false;
  customIcon: string = '';

  oninit(vnode: Mithril.Vnode<IconPickerAttrs>) {
    super.oninit(vnode);
    this.customIcon = this.attrs.value || 'fas fa-award';
  }

  view(): Mithril.Children {
    return (
      <div className="IconPicker">
        <div className="IconPicker-selected">
          <span className="IconPicker-preview">
            <i className={this.attrs.value}></i>
          </span>
          <input
            type="text"
            className="FormControl IconPicker-input"
            value={this.customIcon}
            oninput={(e: InputEvent) => {
              this.customIcon = (e.target as HTMLInputElement).value;
              this.attrs.onchange(this.customIcon);
            }}
            placeholder="fas fa-award"
          />
          <Button
            className="Button Button--icon"
            icon={this.expanded ? 'fas fa-chevron-up' : 'fas fa-chevron-down'}
            onclick={() => {
              this.expanded = !this.expanded;
            }}
            title={
              this.expanded
                ? app.translator.trans('fof-badges.admin.icon_picker.collapse')
                : app.translator.trans('fof-badges.admin.icon_picker.expand')
            }
          />
        </div>

        {this.expanded && (
          <div className="IconPicker-dropdown">
            <p className="IconPicker-hint">{app.translator.trans('fof-badges.admin.icon_picker.hint')}</p>
            <div className="IconPicker-grid">
              {COMMON_ICONS.map((icon) => (
                <button
                  type="button"
                  className={`IconPicker-item ${icon === this.attrs.value ? 'active' : ''}`}
                  onclick={() => this.selectIcon(icon)}
                  title={icon}
                  key={icon}
                >
                  <i className={icon}></i>
                </button>
              ))}
            </div>
          </div>
        )}
      </div>
    );
  }

  selectIcon(icon: string): void {
    this.customIcon = icon;
    this.attrs.onchange(icon);
    this.expanded = false;
  }
}
