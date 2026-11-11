import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import type Mithril from 'mithril';
import type { TriggerConfig, Condition } from '../../common';

interface TriggerBuilderAttrs {
  config: TriggerConfig | null;
  onchange: (config: TriggerConfig | null) => void;
}

interface MetricOption {
  value: string;
  label: string;
}

interface ServerMetric {
  type: string;
  translationKey: string;
  boolean: boolean;
  configFields: unknown;
}

interface OperatorOption {
  value: string;
  label: string;
}

export default class TriggerBuilder extends Component<TriggerBuilderAttrs> {
  config!: TriggerConfig;
  useDateRange: boolean = false;
  useTagFilter: boolean = false;
  tags: any[] = [];
  tagsLoading: boolean = false;

  oninit(vnode: Mithril.Vnode<TriggerBuilderAttrs>) {
    super.oninit(vnode);

    this.config = this.attrs.config || {
      conditions: [],
      logic: 'AND',
      date_range: undefined,
      tag_id: undefined,
    };

    this.useDateRange = !!this.config.date_range;
    this.useTagFilter = !!this.config.tag_id;

    // Load tags if flarum/tags is available
    this.loadTags();
  }

  async loadTags(): Promise<void> {
    // Check if tags extension is enabled
    if (!app.store.models['tags']) {
      return;
    }

    // First check if tags are already in store
    this.tags = app.store.all('tags') as any[];

    // If no tags in store, fetch them
    if (this.tags.length === 0) {
      this.tagsLoading = true;
      m.redraw();

      try {
        const tags = await app.store.find('tags');
        this.tags = Array.isArray(tags) ? tags : [tags];
      } catch (e) {
        console.error('Failed to load tags:', e);
        this.tags = [];
      } finally {
        this.tagsLoading = false;
        m.redraw();
      }
    }
  }

  view(): Mithril.Children {
    const metrics = this.getAvailableMetrics();
    const operators: OperatorOption[] = [
      { value: '>=', label: '>= (greater than or equal)' },
      { value: '<=', label: '<= (less than or equal)' },
      { value: '==', label: '== (equal)' },
      { value: '>', label: '> (greater than)' },
      { value: '<', label: '< (less than)' },
      { value: '!=', label: '!= (not equal)' },
    ];

    return (
      <div className="TriggerBuilder">
        {/* Manual badge info */}
        {this.config.conditions.length === 0 && (
          <div className="TriggerBuilder-info">
            <i className="fas fa-info-circle"></i>
            <span>{app.translator.trans('fof-badges.admin.trigger_builder.no_conditions_info')}</span>
          </div>
        )}

        {/* Conditions */}
        <div className="TriggerBuilder-conditions">
          {this.config.conditions.map((condition, index) => (
            <div className="TriggerBuilder-condition" key={index}>
              <Select
                value={condition.metric}
                options={Object.fromEntries(metrics.map((m) => [m.value, m.label]))}
                onchange={(val: string) => this.updateCondition(index, 'metric', val)}
              />
              <Select
                value={condition.operator}
                options={Object.fromEntries(operators.map((o) => [o.value, o.label]))}
                onchange={(val: string) => this.updateCondition(index, 'operator', val)}
              />
              <input
                type="number"
                className="FormControl TriggerBuilder-value"
                value={condition.value}
                oninput={(e: InputEvent) => {
                  const value = parseInt((e.target as HTMLInputElement).value, 10) || 0;
                  this.updateCondition(index, 'value', value);
                }}
                min={0}
              />
              <Button
                className="Button Button--icon Button--danger"
                icon="fas fa-times"
                onclick={() => this.removeCondition(index)}
                title={app.translator.trans('fof-badges.admin.trigger_builder.remove_condition')}
              />
            </div>
          ))}
        </div>

        <Button className="Button Button--block" icon="fas fa-plus" onclick={() => this.addCondition()}>
          {app.translator.trans('fof-badges.admin.trigger_builder.add_condition')}
        </Button>

        {/* Logic selector (AND/OR) */}
        {this.config.conditions.length > 1 && (
          <div className="TriggerBuilder-logic">
            <label>{app.translator.trans('fof-badges.admin.trigger_builder.logic')}</label>
            <div className="ButtonGroup">
              <Button className={`Button ${this.config.logic === 'AND' ? 'active' : ''}`} onclick={() => this.setLogic('AND')}>
                {app.translator.trans('fof-badges.admin.trigger_builder.and')}
              </Button>
              <Button className={`Button ${this.config.logic === 'OR' ? 'active' : ''}`} onclick={() => this.setLogic('OR')}>
                {app.translator.trans('fof-badges.admin.trigger_builder.or')}
              </Button>
            </div>
            <p className="helpText">
              {this.config.logic === 'AND'
                ? app.translator.trans('fof-badges.admin.trigger_builder.and_help')
                : app.translator.trans('fof-badges.admin.trigger_builder.or_help')}
            </p>
          </div>
        )}

        {/* Date Range */}
        {this.config.conditions.length > 0 && (
          <div className="TriggerBuilder-dateRange">
            <Switch state={this.useDateRange} onchange={(val: boolean) => this.toggleDateRange(val)}>
              {app.translator.trans('fof-badges.admin.trigger_builder.use_date_range')}
            </Switch>
            <p className="helpText">{app.translator.trans('fof-badges.admin.trigger_builder.date_range_help')}</p>

            {this.useDateRange && (
              <div className="TriggerBuilder-dateInputs">
                <div className="Form-group">
                  <label>{app.translator.trans('fof-badges.admin.trigger_builder.date_start')}</label>
                  <input
                    type="datetime-local"
                    className="FormControl"
                    value={this.formatDateForInput(this.config.date_range?.start)}
                    oninput={(e: InputEvent) => {
                      this.setDateRange('start', (e.target as HTMLInputElement).value);
                    }}
                  />
                </div>
                <div className="Form-group">
                  <label>{app.translator.trans('fof-badges.admin.trigger_builder.date_end')}</label>
                  <input
                    type="datetime-local"
                    className="FormControl"
                    value={this.formatDateForInput(this.config.date_range?.end)}
                    oninput={(e: InputEvent) => {
                      this.setDateRange('end', (e.target as HTMLInputElement).value);
                    }}
                  />
                </div>
              </div>
            )}
          </div>
        )}

        {/* Tag Filter (if tags extension available and tag_posts metric is used) */}
        {app.store.models['tags'] && this.hasTagMetric() && (
          <div className="TriggerBuilder-tagFilter">
            <div className="Form-group">
              <label>{app.translator.trans('fof-badges.admin.trigger_builder.tag_filter')}</label>
              {this.tagsLoading ? (
                <div className="TriggerBuilder-tagsLoading">
                  <i className="fas fa-spinner fa-spin"></i> {app.translator.trans('fof-badges.admin.trigger_builder.loading_tags')}
                </div>
              ) : (
                <Select
                  value={String(this.config.tag_id || '')}
                  options={{
                    '': app.translator.trans('fof-badges.admin.trigger_builder.select_tag'),
                    ...Object.fromEntries(this.tags.map((t) => [t.id(), t.name()])),
                  }}
                  onchange={(val: string) => this.setTagId(val ? parseInt(val, 10) : null)}
                />
              )}
              <p className="helpText">{app.translator.trans('fof-badges.admin.trigger_builder.tag_filter_help')}</p>
            </div>
          </div>
        )}
      </div>
    );
  }

  /**
   * The metrics registered on the server whose extensions are enabled, including
   * ones other extensions register, in registration order.
   */
  getAvailableMetrics(): MetricOption[] {
    const metrics = app.forum.attribute<ServerMetric[]>('badgeMetrics') || [];

    return metrics.map((metric) => ({
      value: metric.type,
      label: app.translator.trans(metric.translationKey) as string,
    }));
  }

  hasTagMetric(): boolean {
    return this.config.conditions.some((c) => c.metric === 'tag_posts');
  }

  addCondition(): void {
    this.config.conditions.push({
      metric: 'post_count',
      operator: '>=',
      value: 1,
    });
    this.emitChange();
  }

  removeCondition(index: number): void {
    this.config.conditions.splice(index, 1);

    // If removing the last tag_posts condition, clear tag_id
    if (!this.hasTagMetric()) {
      this.config.tag_id = undefined;
    }

    this.emitChange();
  }

  updateCondition(index: number, field: keyof Condition, value: string | number): void {
    (this.config.conditions[index] as any)[field] = value;

    // If changing metric away from tag_posts and no other tag_posts exist, clear tag_id
    if (field === 'metric' && !this.hasTagMetric()) {
      this.config.tag_id = undefined;
    }

    this.emitChange();
  }

  setLogic(logic: 'AND' | 'OR'): void {
    this.config.logic = logic;
    this.emitChange();
  }

  toggleDateRange(enabled: boolean): void {
    this.useDateRange = enabled;
    this.config.date_range = enabled ? { start: '', end: '' } : undefined;
    this.emitChange();
  }

  setDateRange(field: 'start' | 'end', value: string): void {
    if (!this.config.date_range) {
      this.config.date_range = { start: '', end: '' };
    }
    // Convert local datetime to UTC ISO string for storage
    this.config.date_range[field] = value ? new Date(value).toISOString() : '';
    this.emitChange();
  }

  setTagId(tagId: number | null): void {
    this.config.tag_id = tagId || undefined;
    this.emitChange();
  }

  formatDateForInput(isoString?: string): string {
    if (!isoString) return '';
    // Convert UTC ISO string back to local datetime for the input
    const date = new Date(isoString);
    if (isNaN(date.getTime())) return '';

    const YYYY = date.getFullYear();
    const MM = (date.getMonth() + 1).toString().padStart(2, '0');
    const DD = date.getDate().toString().padStart(2, '0');
    const HH = date.getHours().toString().padStart(2, '0');
    const mm = date.getMinutes().toString().padStart(2, '0');

    return `${YYYY}-${MM}-${DD}T${HH}:${mm}`;
  }

  emitChange(): void {
    // Return null if no conditions (manual badge)
    const config = this.config.conditions.length > 0 ? { ...this.config } : null;

    // Clean up undefined values in config
    if (config) {
      if (!config.date_range?.start && !config.date_range?.end) {
        delete (config as any).date_range;
      }
      if (!config.tag_id) {
        delete (config as any).tag_id;
      }
    }

    this.attrs.onchange(config);
    m.redraw();
  }
}
