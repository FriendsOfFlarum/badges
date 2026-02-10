import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import ColorPreviewInput from 'flarum/common/components/ColorPreviewInput';
import type Mithril from 'mithril';
import { slugify } from '../../common';
import type { Badge, BadgeCategory, TriggerConfig, BadgeActions } from '../../common';
import TriggerBuilder from './TriggerBuilder';
import IconPicker from './IconPicker';

interface BadgeEditModalAttrs extends IInternalModalAttrs {
  badge?: Badge;
  categories: BadgeCategory[];
  onSave?: () => void;
}

export default class BadgeEditModal extends Modal<BadgeEditModalAttrs> {
  badge: Badge | null = null;
  isNew: boolean = true;

  // Form state
  name: string = '';
  slug: string = '';
  description: string = '';
  icon: string = 'fas fa-award';
  iconColor: string = '#ffffff';
  backgroundColor: string = '#667eea';
  categoryId: string | null = null;
  isActive: boolean = true;
  isVisible: boolean = true;
  triggerConfig: TriggerConfig | null = null;
  actions: BadgeActions = { send_notification: true };

  loading: boolean = false;

  oninit(vnode: Mithril.Vnode<BadgeEditModalAttrs>) {
    super.oninit(vnode);

    if (this.attrs.badge) {
      this.badge = this.attrs.badge;
      this.isNew = false;

      // Populate form from existing badge
      this.name = this.badge.name() || '';
      this.slug = this.badge.slug() || '';
      this.description = this.badge.description() || '';
      this.icon = this.badge.icon() || 'fas fa-award';
      this.iconColor = this.badge.iconColor() || '#ffffff';
      this.backgroundColor = this.badge.backgroundColor() || '#667eea';
      this.categoryId = this.badge.categoryId() ? String(this.badge.categoryId()) : null;
      this.isActive = this.badge.isActive();
      this.isVisible = this.badge.isVisible();
      this.triggerConfig = this.badge.triggerConfig() || null;
      this.actions = this.badge.actions() || { send_notification: true };
    }
  }

  className(): string {
    return 'BadgeEditModal Modal--large';
  }

  title(): Mithril.Children {
    return this.isNew ? app.translator.trans('fof-badges.admin.create_badge') : app.translator.trans('fof-badges.admin.edit_badge');
  }

  content(): Mithril.Children {
    const groups = app.store.all('groups').filter((g) => g.id() !== '1' && g.id() !== '2') as any[];

    return (
      <div className="Modal-body">
        <div className="Form">
          {/* Basic Info Section */}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.badge_name')}</label>
            <input
              className="FormControl"
              type="text"
              value={this.name}
              oninput={(e: InputEvent) => {
                this.name = (e.target as HTMLInputElement).value;
                if (this.isNew) {
                  this.slug = slugify(this.name);
                }
              }}
              placeholder={app.translator.trans('fof-badges.admin.badge_name_placeholder')}
            />
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.badge_slug')}</label>
            <input
              className="FormControl"
              type="text"
              value={this.slug}
              oninput={(e: InputEvent) => {
                this.slug = (e.target as HTMLInputElement).value;
              }}
              placeholder={app.translator.trans('fof-badges.admin.badge_slug_placeholder')}
            />
            <p className="helpText">{app.translator.trans('fof-badges.admin.badge_slug_help')}</p>
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.badge_description')}</label>
            <textarea
              className="FormControl"
              value={this.description}
              oninput={(e: InputEvent) => {
                this.description = (e.target as HTMLTextAreaElement).value;
              }}
              rows={3}
              placeholder={app.translator.trans('fof-badges.admin.badge_description_placeholder')}
            />
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.badge_category')}</label>
            <Select
              value={this.categoryId || ''}
              options={{
                '': app.translator.trans('fof-badges.admin.no_category'),
                ...Object.fromEntries(this.attrs.categories.map((c) => [c.id(), c.name()])),
              }}
              onchange={(val: string) => {
                this.categoryId = val || null;
              }}
            />
          </div>

          {/* Appearance Section */}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.appearance')}</label>
            <div className="BadgeEditModal-appearance">
              <div className="BadgeEditModal-preview">
                <span
                  className="BadgePreview"
                  style={{
                    backgroundColor: this.backgroundColor,
                    color: this.iconColor,
                  }}
                >
                  <i className={this.icon}></i>
                  <span>{this.name || 'Badge'}</span>
                </span>
              </div>

              <div className="BadgeEditModal-colors">
                <div className="BadgeEditModal-colorInput">
                  <label>{app.translator.trans('fof-badges.admin.icon_color')}</label>
                  <ColorPreviewInput
                    value={this.iconColor}
                    placeholder="#ffffff"
                    oninput={(e: InputEvent) => {
                      this.iconColor = (e.target as HTMLInputElement).value;
                    }}
                  />
                </div>
                <div className="BadgeEditModal-colorInput">
                  <label>{app.translator.trans('fof-badges.admin.background_color')}</label>
                  <ColorPreviewInput
                    value={this.backgroundColor}
                    placeholder="#667eea"
                    oninput={(e: InputEvent) => {
                      this.backgroundColor = (e.target as HTMLInputElement).value;
                    }}
                  />
                </div>
              </div>

              <div className="BadgeEditModal-iconPicker">
                <IconPicker value={this.icon} onchange={(icon: string) => (this.icon = icon)} />
              </div>
            </div>
          </div>

          {/* Trigger Configuration Section */}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.trigger_config')}</label>
            <p className="helpText">{app.translator.trans('fof-badges.admin.trigger_config_help')}</p>
            <TriggerBuilder
              config={this.triggerConfig}
              onchange={(config: TriggerConfig | null) => {
                this.triggerConfig = config;
              }}
            />
          </div>

          {/* Actions Section */}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.actions')}</label>
            <div className="BadgeEditModal-actions">
              <Switch
                state={this.actions.send_notification !== false}
                onchange={(val: boolean) => {
                  this.actions = { ...this.actions, send_notification: val };
                }}
              >
                {app.translator.trans('fof-badges.admin.send_notification')}
              </Switch>

              <div className="BadgeEditModal-groupSelect">
                <label>{app.translator.trans('fof-badges.admin.add_to_group')}</label>
                <Select
                  value={String(this.actions.add_to_group || '')}
                  options={{
                    '': app.translator.trans('fof-badges.admin.no_group'),
                    ...Object.fromEntries(groups.map((g) => [g.id(), g.namePlural()])),
                  }}
                  onchange={(val: string) => {
                    this.actions = {
                      ...this.actions,
                      add_to_group: val ? parseInt(val, 10) : null,
                    };
                  }}
                />
                <p className="helpText">{app.translator.trans('fof-badges.admin.add_to_group_help')}</p>
              </div>
            </div>
          </div>

          {/* Status Section */}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.status')}</label>
            <div className="BadgeEditModal-status">
              <Switch
                state={this.isActive}
                onchange={(val: boolean) => {
                  this.isActive = val;
                }}
              >
                {app.translator.trans('fof-badges.admin.is_active')}
              </Switch>
              <p className="helpText">{app.translator.trans('fof-badges.admin.is_active_help')}</p>

              <Switch
                state={this.isVisible}
                onchange={(val: boolean) => {
                  this.isVisible = val;
                }}
              >
                {app.translator.trans('fof-badges.admin.is_visible')}
              </Switch>
              <p className="helpText">{app.translator.trans('fof-badges.admin.is_visible_help')}</p>
            </div>
          </div>

          {/* Validation Errors */}
          {this.getValidationError() && (
            <div className="Form-group">
              <div className="Alert Alert--error">
                <i className="fas fa-exclamation-circle"></i> {this.getValidationError()}
              </div>
            </div>
          )}

          {/* Submit Buttons */}
          <div className="Form-group">
            <Button className="Button Button--primary" loading={this.loading} disabled={!this.canSave()} onclick={() => this.save()}>
              {app.translator.trans('fof-badges.admin.save')}
            </Button>{' '}
            <Button className="Button" onclick={() => this.hide()}>
              {app.translator.trans('fof-badges.admin.cancel')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();
    this.save();
  }

  async save(): Promise<void> {
    this.loading = true;
    m.redraw();

    try {
      const data: Record<string, unknown> = {
        name: this.name,
        slug: this.slug || slugify(this.name),
        description: this.description || null,
        icon: this.icon,
        iconColor: this.iconColor,
        backgroundColor: this.backgroundColor,
        isActive: this.isActive,
        isVisible: this.isVisible,
        triggerConfig: this.triggerConfig,
        actions: this.actions,
        categoryId: this.categoryId || null,
      };

      if (this.isNew) {
        await app.store.createRecord('badges').save(data);
      } else if (this.badge) {
        await this.badge.save(data);
      }

      this.attrs.onSave?.();
      this.hide();
    } catch (e) {
      this.loading = false;
      m.redraw();
    }
  }

  /**
   * Check if the form can be saved
   */
  canSave(): boolean {
    if (!this.name.trim()) return false;

    // If tag_posts metric is used, tag_id must be selected
    if (this.hasTagMetric() && !this.triggerConfig?.tag_id) {
      return false;
    }

    return true;
  }

  /**
   * Get validation error message if any
   */
  getValidationError(): string | null {
    if (this.hasTagMetric() && !this.triggerConfig?.tag_id) {
      return app.translator.trans('fof-badges.admin.validation.tag_required') as string;
    }

    return null;
  }

  /**
   * Check if trigger config contains tag_posts metric
   */
  hasTagMetric(): boolean {
    return this.triggerConfig?.conditions?.some((c) => c.metric === 'tag_posts') ?? false;
  }
}
