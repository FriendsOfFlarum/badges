import Form from 'flarum/common/components/Form';
import app from 'flarum/admin/app';
import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import type Mithril from 'mithril';
import { slugify } from '../../common';
import type { BadgeCategory } from '../../common';

interface CategoryEditModalAttrs extends IFormModalAttrs {
  category?: BadgeCategory;
  onSave?: () => void;
}

export default class CategoryEditModal extends FormModal<CategoryEditModalAttrs> {
  category: BadgeCategory | null = null;
  isNew: boolean = true;

  // Form state
  name: string = '';
  slug: string = '';
  description: string = '';
  isEnabled: boolean = true;

  loading: boolean = false;

  oninit(vnode: Mithril.Vnode<CategoryEditModalAttrs>) {
    super.oninit(vnode);

    if (this.attrs.category) {
      this.category = this.attrs.category;
      this.isNew = false;
      this.name = this.category.name() || '';
      this.slug = this.category.slug() || '';
      this.description = this.category.description() || '';
      this.isEnabled = this.category.isEnabled();
    }
  }

  className(): string {
    return 'CategoryEditModal';
  }

  title(): Mithril.Children {
    return this.isNew ? app.translator.trans('fof-badges.admin.create_category') : app.translator.trans('fof-badges.admin.edit_category');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        <Form>
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.category_name')}</label>
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
              placeholder={app.translator.trans('fof-badges.admin.category_name_placeholder')}
            />
          </div>
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.category_slug')}</label>
            <input
              className="FormControl"
              type="text"
              value={this.slug}
              oninput={(e: InputEvent) => {
                this.slug = (e.target as HTMLInputElement).value;
              }}
              placeholder={app.translator.trans('fof-badges.admin.category_slug_placeholder')}
            />
            <p className="helpText">{app.translator.trans('fof-badges.admin.category_slug_help')}</p>
          </div>
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.category_description')}</label>
            <textarea
              className="FormControl"
              value={this.description}
              oninput={(e: InputEvent) => {
                this.description = (e.target as HTMLTextAreaElement).value;
              }}
              rows={3}
              placeholder={app.translator.trans('fof-badges.admin.category_description_placeholder')}
            />
          </div>
          <div className="Form-group">
            <Switch
              state={this.isEnabled}
              onchange={(val: boolean) => {
                this.isEnabled = val;
              }}
            >
              {app.translator.trans('fof-badges.admin.category_enabled')}
            </Switch>
            <p className="helpText">{app.translator.trans('fof-badges.admin.category_enabled_help')}</p>
          </div>
          {}
          <div className="Form-group">
            <Button className="Button Button--primary" loading={this.loading} disabled={!this.name.trim()} onclick={() => this.save()}>
              {app.translator.trans('fof-badges.admin.save')}
            </Button>{' '}
            <Button className="Button" onclick={() => this.hide()}>
              {app.translator.trans('fof-badges.admin.cancel')}
            </Button>
          </div>
        </Form>
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
      const data = {
        name: this.name,
        slug: this.slug || slugify(this.name),
        description: this.description || null,
        isEnabled: this.isEnabled,
      };

      if (this.isNew) {
        await app.store.createRecord('badge-categories').save(data);
      } else if (this.category) {
        await this.category.save(data);
      }

      this.attrs.onSave?.();
      this.hide();
    } catch (e) {
      this.loading = false;
      m.redraw();
      throw e;
    }
  }
}
