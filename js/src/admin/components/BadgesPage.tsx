import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type { Badge, BadgeCategory } from '../../common';
import getRarityInfo from '../../common/helpers/getRarityInfo';
import BadgeEditModal from './BadgeEditModal';
import CategoryEditModal from './CategoryEditModal';
import ManualAssignModal from './ManualAssignModal';

interface RecalculationProgress {
  id: number;
  status: 'pending' | 'running' | 'completed' | 'failed' | 'cancelled';
  totalUsers: number;
  processedUsers: number;
  totalBadges: number;
  awarded: number;
  revoked: number;
  skipped: number;
  percentage: number;
  totalChunks: number;
  processedChunks: number;
  chunkPercentage: number;
  chunkSize: number;
  startedAt: string | null;
  completedAt: string | null;
  errorMessage: string | null;
}

interface RecalculationJob {
  id: number;
  status: string;
  totalUsers: number;
  processedUsers: number;
  totalBadges: number;
  awarded: number;
  revoked: number;
  skipped: number;
  percentage: number;
  totalChunks: number;
  processedChunks: number;
  chunkSize: number;
  startedAt: string | null;
  completedAt: string | null;
  errorMessage: string | null;
  createdAt: string;
}

export default class BadgesPage extends ExtensionPage {
  loading: boolean = true;
  recalculating: boolean = false;
  badges: Badge[] = [];
  categories: BadgeCategory[] = [];
  activeTab: 'badges' | 'categories' | 'recalculation' | 'settings' = 'badges';
  recalculationProgress: RecalculationProgress | null = null;
  pollInterval: ReturnType<typeof setInterval> | null = null;

  // Recalculation form state
  selectedBadgeId: string = '';
  chunkSize: number = 100;
  noRevoke: boolean = true;
  reapplyActions: boolean = false;
  startingRecalculation: boolean = false;
  autoRefresh: boolean = true;

  // Jobs list
  jobs: RecalculationJob[] = [];
  loadingJobs: boolean = false;
  jobsDisplayCount: number = 5;

  // Sync counts
  syncingCounts: boolean = false;

  // Settings state
  savingSettings: boolean = false;

  // Install defaults
  installingDefaults: boolean = false;

  // Lifecycle flag to prevent polling after component is destroyed
  destroyed: boolean = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.loadData();
    this.checkRecalculationStatus();
  }

  onremove(vnode: Mithril.VnodeDOM) {
    super.onremove(vnode);
    this.destroyed = true;
    this.stopPolling();
  }

  startPolling() {
    if (this.pollInterval || !this.autoRefresh || this.destroyed) return;
    this.pollInterval = setInterval(() => this.checkRecalculationStatus(), 5000);
  }

  stopPolling() {
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
  }

  async checkRecalculationStatus() {
    if (this.destroyed) return;

    try {
      const response = await app.request<{
        hasProgress: boolean;
        progress: RecalculationProgress | null;
      }>({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/badges/recalculate/status',
      });

      this.recalculationProgress = response.progress;

      if (response.progress) {
        const status = response.progress.status;
        if (status === 'pending' || status === 'running') {
          this.recalculating = true;
          this.startPolling();
        } else {
          this.recalculating = false;
          this.stopPolling();

          if (status === 'completed') {
            this.loadData();
            this.loadJobs();
          }
        }
      } else {
        this.recalculating = false;
        this.stopPolling();
      }

      m.redraw();
    } catch (error) {
      console.error('Failed to check recalculation status:', error);
    }
  }

  async loadData() {
    this.loading = true;
    m.redraw();

    try {
      const [badges, categories] = await Promise.all([app.store.find<Badge[]>('badges'), app.store.find<BadgeCategory[]>('badge-categories')]);
      // Filter to ensure only valid models are stored and sort by order
      this.badges = (Array.isArray(badges) ? badges : [])
        .filter((b) => b && typeof b.id === 'function')
        .sort((a, b) => (a.order() || 0) - (b.order() || 0)) as Badge[];
      this.categories = (Array.isArray(categories) ? categories : [])
        .filter((c) => c && typeof c.id === 'function')
        .sort((a, b) => (a.order() || 0) - (b.order() || 0)) as BadgeCategory[];
    } catch (error) {
      console.error('Failed to load badges data:', error);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  async loadJobs() {
    this.loadingJobs = true;
    m.redraw();

    try {
      const response = await app.request<{ jobs: RecalculationJob[] }>({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/badges/recalculate/jobs',
      });
      this.jobs = response.jobs || [];
      this.jobsDisplayCount = 5;
    } catch (error) {
      console.error('Failed to load jobs:', error);
      this.jobs = [];
    } finally {
      this.loadingJobs = false;
      m.redraw();
    }
  }

  content() {
    if (this.loading) {
      return (
        <div className="BadgesPage">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="BadgesPage">
        <div className="BadgesPage-header">
          <div className="BadgesPage-tabs">
            <Button
              className={`Button ${this.activeTab === 'badges' ? 'active' : ''}`}
              onclick={() => {
                this.activeTab = 'badges';
                m.redraw();
              }}
            >
              <i className="fas fa-award" />
              {app.translator.trans('fof-badges.admin.tabs.badges')}
              <span className="Button-badge">{this.badges.length}</span>
            </Button>
            <Button
              className={`Button ${this.activeTab === 'categories' ? 'active' : ''}`}
              onclick={() => {
                this.activeTab = 'categories';
                m.redraw();
              }}
            >
              <i className="fas fa-folder" />
              {app.translator.trans('fof-badges.admin.tabs.categories')}
              <span className="Button-badge">{this.categories.length}</span>
            </Button>
            <Button
              className={`Button ${this.activeTab === 'recalculation' ? 'active' : ''}`}
              onclick={() => {
                this.activeTab = 'recalculation';
                this.loadJobs();
                m.redraw();
              }}
            >
              <i className={this.recalculating ? 'fas fa-spinner fa-spin' : 'fas fa-sync-alt'} />
              {app.translator.trans('fof-badges.admin.tabs.recalculation')}
            </Button>
            <Button
              className={`Button ${this.activeTab === 'settings' ? 'active' : ''}`}
              onclick={() => {
                this.activeTab = 'settings';
                m.redraw();
              }}
            >
              <i className="fas fa-cog" />
              {app.translator.trans('fof-badges.admin.tabs.settings')}
            </Button>
          </div>
          <div className="BadgesPage-actions">
            {this.activeTab === 'badges' && (
              <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.openBadgeModal()}>
                {app.translator.trans('fof-badges.admin.create_badge')}
              </Button>
            )}
            {this.activeTab === 'categories' && (
              <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.openCategoryModal()}>
                {app.translator.trans('fof-badges.admin.create_category')}
              </Button>
            )}
          </div>
        </div>

        <div className="BadgesPage-content">
          {this.activeTab === 'badges' && this.badgesList()}
          {this.activeTab === 'categories' && this.categoriesList()}
          {this.activeTab === 'recalculation' && this.recalculationTab()}
          {this.activeTab === 'settings' && this.settingsTab()}
        </div>
      </div>
    );
  }

  recalculationTab(): Mithril.Children {
    const userCount = app.forum.attribute<number>('userCount') || 0;
    const estimatedChunks = Math.ceil(userCount / this.chunkSize);
    const automaticBadges = this.badges.filter((b) => b.isActive() && b.triggerConfig());

    // Manual badges with actions (for re-apply actions feature)
    const manualBadgesWithActions = this.badges.filter((b) => {
      if (!b.isActive() || b.triggerConfig()) return false;
      const actions = b.actions();
      return actions && (actions.send_notification || actions.add_to_group);
    });

    // Build badge options
    const badgeOptions: Record<string, string> = {
      '': app.translator.trans('fof-badges.admin.recalculate_tab.all_badges') as string,
    };
    automaticBadges.forEach((badge) => {
      badgeOptions[badge.id() as string] = badge.name();
    });

    // Add manual badges with actions (marked as manual)
    manualBadgesWithActions.forEach((badge) => {
      badgeOptions[badge.id() as string] = `${badge.name()} (${app.translator.trans('fof-badges.admin.manual')})`;
    });

    // Chunk size options
    const chunkSizeOptions: Record<string, string> = {
      '100': `100 (${app.translator.trans('fof-badges.admin.recalculate_tab.default')})`,
      '250': '250',
      '500': '500',
      '1000': '1000',
      '2000': '2000',
    };

    const isActive = this.recalculationProgress && ['pending', 'running'].includes(this.recalculationProgress.status);

    return (
      <div className="RecalculationTab">
        {/* Current Progress */}
        {this.recalculationProgress && this.recalculationProgressBar()}

        {/* Start New Recalculation Form */}
        <div className="RecalculationTab-form">
          <h3>{app.translator.trans('fof-badges.admin.recalculate_tab.title')}</h3>

          <div className="RecalculationTab-info Alert">
            <i className="fas fa-info-circle"></i>
            <span>{app.translator.trans('fof-badges.admin.recalculate_tab.info')}</span>
          </div>

          <div className="Form">
            {/* Badge Selection */}
            <div className="Form-group">
              <label>{app.translator.trans('fof-badges.admin.recalculate_tab.badge_label')}</label>
              {Select.component({
                options: badgeOptions,
                value: this.selectedBadgeId,
                onchange: (value: string) => {
                  this.selectedBadgeId = value;
                },
                disabled: isActive,
              })}
              <p className="helpText">{app.translator.trans('fof-badges.admin.recalculate_tab.badge_help')}</p>
            </div>

            {/* Chunk Size */}
            <div className="Form-group">
              <label>{app.translator.trans('fof-badges.admin.recalculate_tab.chunk_size_label')}</label>
              {Select.component({
                options: chunkSizeOptions,
                value: String(this.chunkSize),
                onchange: (value: string) => {
                  this.chunkSize = parseInt(value, 10);
                },
                disabled: isActive,
              })}
              <p className="helpText">{app.translator.trans('fof-badges.admin.recalculate_tab.chunk_size_help')}</p>
            </div>

            {/* No Revoke Option */}
            <div className="Form-group">
              {Switch.component(
                {
                  state: this.noRevoke,
                  onchange: (value: boolean) => {
                    this.noRevoke = value;
                  },
                  disabled: isActive,
                },
                app.translator.trans('fof-badges.admin.recalculate_tab.no_revoke_label')
              )}
              <p className="helpText">{app.translator.trans('fof-badges.admin.recalculate_tab.no_revoke_help')}</p>
            </div>

            {/* Re-apply Actions Option */}
            <div className="Form-group">
              {Switch.component(
                {
                  state: this.reapplyActions,
                  onchange: (value: boolean) => {
                    this.reapplyActions = value;
                  },
                  disabled: isActive,
                },
                app.translator.trans('fof-badges.admin.recalculate_tab.reapply_actions_label')
              )}
              <p className="helpText">{app.translator.trans('fof-badges.admin.recalculate_tab.reapply_actions_help')}</p>
            </div>

            {/* Statistics */}
            <div className="Form-group">
              <div className="RecalculationTab-stats">
                <div className="RecalculationTab-stat">
                  <span className="RecalculationTab-statLabel">{app.translator.trans('fof-badges.admin.recalculate_tab.total_users')}</span>
                  <span className="RecalculationTab-statValue">{userCount.toLocaleString()}</span>
                </div>
                <div className="RecalculationTab-stat">
                  <span className="RecalculationTab-statLabel">{app.translator.trans('fof-badges.admin.recalculate_tab.estimated_chunks')}</span>
                  <span className="RecalculationTab-statValue">{estimatedChunks.toLocaleString()}</span>
                </div>
                <div className="RecalculationTab-stat">
                  <span className="RecalculationTab-statLabel">{app.translator.trans('fof-badges.admin.recalculate_tab.badges_to_check')}</span>
                  <span className="RecalculationTab-statValue">{this.selectedBadgeId ? 1 : automaticBadges.length}</span>
                </div>
              </div>
            </div>

            {/* Start Button */}
            <div className="Form-group">
              <Button
                className="Button Button--primary"
                icon="fas fa-play"
                loading={this.startingRecalculation}
                disabled={isActive || automaticBadges.length === 0}
                onclick={() => this.startRecalculation()}
              >
                {app.translator.trans('fof-badges.admin.recalculate_tab.start')}
              </Button>
              {automaticBadges.length === 0 && (
                <p className="helpText RecalculationTab-warning">{app.translator.trans('fof-badges.admin.recalculate_tab.no_automatic_badges')}</p>
              )}
            </div>
          </div>
        </div>

        {/* Sync Badge Counts */}
        <div className="RecalculationTab-sync">
          <h3>{app.translator.trans('fof-badges.admin.recalculate_tab.sync_counts_title')}</h3>
          <div className="RecalculationTab-info Alert">
            <i className="fas fa-info-circle"></i>
            <span>{app.translator.trans('fof-badges.admin.recalculate_tab.sync_counts_info')}</span>
          </div>
          <Button className="Button Button--primary" icon="fas fa-sync" loading={this.syncingCounts} onclick={() => this.syncBadgeCounts()}>
            {app.translator.trans('fof-badges.admin.recalculate_tab.sync_counts')}
          </Button>
        </div>

        {/* Jobs History */}
        <div className="RecalculationTab-jobs">
          <h3>{app.translator.trans('fof-badges.admin.recalculate_tab.jobs_title')}</h3>
          {this.jobsList()}
        </div>
      </div>
    );
  }

  settingsTab(): Mithril.Children {
    const primaryDisplayOptions: Record<string, string> = {
      hidden: app.translator.trans('fof-badges.admin.settings.primary_badge_hidden') as string,
      icon: app.translator.trans('fof-badges.admin.settings.primary_badge_icon') as string,
      icon_name: app.translator.trans('fof-badges.admin.settings.primary_badge_icon_name') as string,
    };

    return (
      <div className="SettingsTab">
        <div className="SettingsTab-section">
          <h3>{app.translator.trans('fof-badges.admin.settings.display_title')}</h3>

          {/* New Badge Highlight */}
          <div className="Form-group">
            {Switch.component(
              {
                state: this.setting('fof-badges.new_badge_highlight')() !== '0',
                onchange: (value: boolean) => {
                  this.setting('fof-badges.new_badge_highlight')(value ? '1' : '0');
                },
              },
              app.translator.trans('fof-badges.admin.settings.new_badge_highlight')
            )}
            <p className="helpText">{app.translator.trans('fof-badges.admin.settings.new_badge_highlight_help')}</p>
          </div>

          {/* Primary Badge Display */}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.settings.primary_badge_display')}</label>
            {Select.component({
              options: primaryDisplayOptions,
              value: this.setting('fof-badges.primary_badge_display')() || 'icon',
              onchange: (value: string) => {
                this.setting('fof-badges.primary_badge_display')(value);
              },
            })}
            <p className="helpText">{app.translator.trans('fof-badges.admin.settings.primary_badge_display_help')}</p>
          </div>

          {/* Show Badges on User Card */}
          <div className="Form-group">
            {Switch.component(
              {
                state: this.setting('fof-badges.show_badges_on_user_card')() !== '0',
                onchange: (value: boolean) => {
                  this.setting('fof-badges.show_badges_on_user_card')(value ? '1' : '0');
                },
              },
              app.translator.trans('fof-badges.admin.settings.show_badges_on_user_card')
            )}
            <p className="helpText">{app.translator.trans('fof-badges.admin.settings.show_badges_on_user_card_help')}</p>
          </div>

          {/* Post Badge Display Limit */}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.admin.settings.post_badge_display_limit')}</label>
            <input
              type="number"
              className="FormControl"
              min="0"
              max="10"
              value={this.setting('fof-badges.badge_display_limit')() || '3'}
              oninput={(e: InputEvent) => {
                const value = Math.min(10, Math.max(0, parseInt((e.target as HTMLInputElement).value) || 0));
                this.setting('fof-badges.badge_display_limit')(String(value));
              }}
            />
            <p className="helpText">{app.translator.trans('fof-badges.admin.settings.post_badge_display_limit_help')}</p>
          </div>
        </div>

        {/* Save Button */}
        <div className="Form-group">
          <Button className="Button Button--primary" loading={this.savingSettings} onclick={() => this.saveSettings()}>
            {app.translator.trans('fof-badges.admin.settings.save')}
          </Button>
        </div>

        {/* Extension Integrations */}
        {this.integrationsSection()}
      </div>
    );
  }

  integrationsSection(): Mithril.Children {
    const integrations = [
      { key: 'likesExtensionEnabled', name: 'flarum/likes', metrics: ['likes_received', 'likes_given'] },
      { key: 'nicknamesExtensionEnabled', name: 'flarum/nicknames', metrics: ['has_nickname'] },
      { key: 'userBioExtensionEnabled', name: 'fof/user-bio', metrics: ['has_bio'] },
      { key: 'bestAnswerExtensionEnabled', name: 'fof/best-answer', metrics: ['best_answers_received'] },
      { key: 'uploadExtensionEnabled', name: 'fof/upload', metrics: ['files_uploaded'] },
      { key: 'pollsExtensionEnabled', name: 'fof/polls', metrics: ['polls_created', 'polls_voted'] },
      { key: 'byobuExtensionEnabled', name: 'fof/byobu', metrics: ['private_discussions_created'] },
      { key: 'reactionsExtensionEnabled', name: 'fof/reactions', metrics: ['reactions_received', 'reactions_given'] },
      {
        key: 'gamificationExtensionEnabled',
        name: 'fof/gamification',
        metrics: ['upvotes_received', 'upvotes_given', 'downvotes_received', 'downvotes_given'],
      },
    ];

    return (
      <div className="SettingsTab-section SettingsTab-integrations">
        <h3>{app.translator.trans('fof-badges.admin.settings.integrations_title')}</h3>
        <p className="helpText">{app.translator.trans('fof-badges.admin.settings.integrations_help')}</p>
        <div className="IntegrationsList">
          {integrations.map((ext) => {
            const enabled = app.forum.attribute(ext.key) as boolean;
            return (
              <div
                className={`IntegrationsList-item ${enabled ? 'IntegrationsList-item--enabled' : 'IntegrationsList-item--disabled'}`}
                key={ext.key}
              >
                <span className="IntegrationsList-status">
                  <i className={enabled ? 'fas fa-check-circle' : 'fas fa-times-circle'}></i>
                </span>
                <span className="IntegrationsList-name">{ext.name}</span>
                <span className="IntegrationsList-metrics">
                  {ext.metrics.map((m) => app.translator.trans(`fof-badges.admin.metrics.${m}`)).join(', ')}
                </span>
              </div>
            );
          })}
        </div>
      </div>
    );
  }

  async saveSettings(): Promise<void> {
    this.savingSettings = true;
    m.redraw();

    try {
      await this.saveSettingsToDatabase();
      app.alerts.show({ type: 'success' }, app.translator.trans('fof-badges.admin.settings.saved'));
    } catch (error) {
      console.error('Failed to save settings:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('fof-badges.admin.settings.save_error'));
    } finally {
      this.savingSettings = false;
      m.redraw();
    }
  }

  async saveSettingsToDatabase(): Promise<void> {
    const settings = {
      'fof-badges.new_badge_highlight': this.setting('fof-badges.new_badge_highlight')(),
      'fof-badges.primary_badge_display': this.setting('fof-badges.primary_badge_display')(),
      'fof-badges.show_badges_on_user_card': this.setting('fof-badges.show_badges_on_user_card')(),
      'fof-badges.badge_display_limit': this.setting('fof-badges.badge_display_limit')(),
    };

    await app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/settings',
      body: settings,
    });
  }

  recalculationProgressBar(): Mithril.Children {
    const progress = this.recalculationProgress;
    if (!progress) return null;

    const isActive = progress.status === 'pending' || progress.status === 'running';
    const isCompleted = progress.status === 'completed';
    const isFailed = progress.status === 'failed';
    const isCancelled = progress.status === 'cancelled';

    let statusClass = 'RecalculationProgress--running';
    let statusIcon = 'fas fa-spinner fa-spin';
    let statusText = app.translator.trans('fof-badges.admin.recalculation.running');

    if (isCompleted) {
      statusClass = 'RecalculationProgress--completed';
      statusIcon = 'fas fa-check';
      statusText = app.translator.trans('fof-badges.admin.recalculation.completed');
    } else if (isFailed) {
      statusClass = 'RecalculationProgress--failed';
      statusIcon = 'fas fa-times';
      statusText = app.translator.trans('fof-badges.admin.recalculation.failed');
    } else if (isCancelled) {
      statusClass = 'RecalculationProgress--cancelled';
      statusIcon = 'fas fa-ban';
      statusText = app.translator.trans('fof-badges.admin.recalculation.cancelled');
    } else if (progress.status === 'pending') {
      statusText = app.translator.trans('fof-badges.admin.recalculation.pending');
    }

    return (
      <div className={`RecalculationProgress ${statusClass}`}>
        <div className="RecalculationProgress-header">
          <span className="RecalculationProgress-status">
            <i className={statusIcon}></i> {statusText}
          </span>
          <div className="RecalculationProgress-actions">
            {isActive && (
              <Button
                className={`Button Button--small ${this.autoRefresh ? 'Button--icon' : ''}`}
                icon={this.autoRefresh ? 'fas fa-sync fa-spin' : 'fas fa-sync'}
                onclick={() => {
                  this.autoRefresh = !this.autoRefresh;
                  if (this.autoRefresh) {
                    this.startPolling();
                  } else {
                    this.stopPolling();
                  }
                }}
                title={app.translator.trans(
                  this.autoRefresh ? 'fof-badges.admin.recalculation.auto_refresh_on' : 'fof-badges.admin.recalculation.auto_refresh_off'
                )}
              >
                {!this.autoRefresh && app.translator.trans('fof-badges.admin.recalculation.auto_refresh_off')}
              </Button>
            )}
            {isActive && (
              <Button className="Button Button--danger Button--small" icon="fas fa-stop" onclick={() => this.cancelRecalculation()}>
                {app.translator.trans('fof-badges.admin.recalculation.cancel')}
              </Button>
            )}
            {!isActive && (
              <Button className="Button Button--small" icon="fas fa-times" onclick={() => this.dismissProgress()}>
                {app.translator.trans('fof-badges.admin.recalculation.dismiss')}
              </Button>
            )}
          </div>
        </div>

        <div className="RecalculationProgress-bar">
          <div className="RecalculationProgress-barFill" style={{ width: `${progress.percentage ?? 0}%` }}></div>
        </div>

        <div className="RecalculationProgress-stats">
          <span>
            {app.translator.trans('fof-badges.admin.recalculation.progress', {
              processed: (progress.processedUsers ?? 0).toLocaleString(),
              total: (progress.totalUsers ?? 0).toLocaleString(),
              percentage: (progress.percentage ?? 0).toFixed(1),
            })}
          </span>
          {progress.totalChunks > 0 && (
            <span>
              {app.translator.trans('fof-badges.admin.recalculation.chunks', {
                processed: (progress.processedChunks ?? 0).toLocaleString(),
                total: (progress.totalChunks ?? 0).toLocaleString(),
              })}
            </span>
          )}
          <span>
            {app.translator.trans('fof-badges.admin.recalculation.stats', {
              awarded: (progress.awarded ?? 0).toLocaleString(),
              revoked: (progress.revoked ?? 0).toLocaleString(),
            })}
          </span>
        </div>

        {progress.errorMessage && <div className="RecalculationProgress-error">{progress.errorMessage}</div>}
      </div>
    );
  }

  jobsList(): Mithril.Children {
    if (this.loadingJobs) {
      return <LoadingIndicator />;
    }

    if (this.jobs.length === 0) {
      return <div className="RecalculationTab-jobsEmpty">{app.translator.trans('fof-badges.admin.recalculate_tab.jobs_empty')}</div>;
    }

    const visibleJobs = this.jobs.slice(0, this.jobsDisplayCount);

    return (
      <div className="RecalculationTab-jobsList">
        {visibleJobs.map((job) => this.jobItem(job))}
        {this.jobs.length > this.jobsDisplayCount && (
          <Button
            className="Button RecalculationTab-showMore"
            onclick={() => {
              this.jobsDisplayCount += 5;
              m.redraw();
            }}
          >
            {app.translator.trans('fof-badges.admin.recalculate_tab.show_more', { count: 5 })}
          </Button>
        )}
      </div>
    );
  }

  jobItem(job: RecalculationJob): Mithril.Children {
    const isActive = job.status === 'pending' || job.status === 'running';
    const statusKey = `fof-badges.admin.recalculation_jobs.status.${job.status}`;

    return (
      <div className={`RecalculationTab-job RecalculationTab-job--${job.status}`} key={job.id}>
        <div className="RecalculationTab-jobHeader">
          <span className="RecalculationTab-jobStatus">{app.translator.trans(statusKey)}</span>
          <span className="RecalculationTab-jobDate">{job.createdAt ? new Date(job.createdAt).toLocaleString() : ''}</span>
        </div>
        <div className="RecalculationTab-jobStats">
          <span>
            {app.translator.trans('fof-badges.admin.recalculation_jobs.users', {
              processed: (job.processedUsers ?? 0).toLocaleString(),
              total: (job.totalUsers ?? 0).toLocaleString(),
            })}
          </span>
          {job.totalChunks > 0 && (
            <span>
              {app.translator.trans('fof-badges.admin.recalculation.chunks', {
                processed: (job.processedChunks ?? 0).toLocaleString(),
                total: (job.totalChunks ?? 0).toLocaleString(),
              })}
            </span>
          )}
          <span>
            {app.translator.trans('fof-badges.admin.recalculation_jobs.results', {
              awarded: (job.awarded ?? 0).toLocaleString(),
              revoked: (job.revoked ?? 0).toLocaleString(),
            })}
          </span>
        </div>
        {job.errorMessage && <div className="RecalculationTab-jobError">{job.errorMessage}</div>}
        {isActive && (
          <div className="RecalculationTab-jobActions">
            <Button className="Button Button--danger Button--small" icon="fas fa-stop" onclick={() => this.cancelJob(job.id)}>
              {app.translator.trans('fof-badges.admin.recalculation_jobs.cancel')}
            </Button>
          </div>
        )}
      </div>
    );
  }

  dismissProgress() {
    this.recalculationProgress = null;
    m.redraw();
  }

  async cancelRecalculation() {
    if (!confirm(extractText(app.translator.trans('fof-badges.admin.recalculation.cancel_confirm')))) {
      return;
    }

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/badges/recalculate/cancel',
      });

      app.alerts.show({ type: 'success' }, app.translator.trans('fof-badges.admin.recalculation.cancelled'));
      this.checkRecalculationStatus();
      this.loadJobs();
    } catch (error) {
      console.error('Failed to cancel recalculation:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('fof-badges.admin.recalculation.cancel_error'));
    }
  }

  async cancelJob(jobId: number) {
    if (!confirm(extractText(app.translator.trans('fof-badges.admin.recalculation.cancel_confirm')))) {
      return;
    }

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/badges/recalculate/cancel',
        body: { jobId },
      });

      app.alerts.show({ type: 'success' }, app.translator.trans('fof-badges.admin.recalculation_jobs.cancelled'));
      this.loadJobs();
      this.checkRecalculationStatus();
    } catch (error) {
      console.error('Failed to cancel job:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('fof-badges.admin.recalculation_jobs.cancel_error'));
    }
  }

  async startRecalculation() {
    this.startingRecalculation = true;
    m.redraw();

    try {
      const body: Record<string, unknown> = {
        chunkSize: this.chunkSize,
        noRevoke: this.noRevoke,
        reapplyActions: this.reapplyActions,
      };

      if (this.selectedBadgeId) {
        body.badgeId = this.selectedBadgeId;
      }

      const response = await app.request<{
        success: boolean;
        error?: string;
        message: string;
        progress?: RecalculationProgress;
      }>({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/badges/recalculate',
        body,
      });

      if (!response.success && response.error === 'already_running') {
        app.alerts.show({ type: 'warning' }, app.translator.trans('fof-badges.admin.recalculation.already_running'));
        if (response.progress) {
          this.recalculationProgress = response.progress;
        }
      } else if (response.success) {
        app.alerts.show({ type: 'success' }, app.translator.trans('fof-badges.admin.recalculation.started'));
        if (response.progress) {
          this.recalculationProgress = response.progress;
        }
        this.recalculating = true;
        this.startPolling();
      }
    } catch (error) {
      console.error('Failed to start recalculation:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('fof-badges.admin.recalculate_error'));
    } finally {
      this.startingRecalculation = false;
      m.redraw();
    }
  }

  async syncBadgeCounts() {
    this.syncingCounts = true;
    m.redraw();

    try {
      const response = await app.request<{
        success: boolean;
        total: number;
        corrected: number;
      }>({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/badges/sync-counts',
      });

      if (response.success) {
        app.alerts.show(
          { type: 'success' },
          app.translator.trans('fof-badges.admin.recalculate_tab.sync_counts_success', {
            total: response.total,
            corrected: response.corrected,
          })
        );
        this.loadData();
      }
    } catch (error) {
      console.error('Failed to sync badge counts:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('fof-badges.admin.recalculate_tab.sync_counts_error'));
    } finally {
      this.syncingCounts = false;
      m.redraw();
    }
  }

  badgesList(): Mithril.Children {
    if (this.badges.length === 0) {
      return (
        <div className="EmptyState">
          <div className="EmptyState-icon">
            <i className="fas fa-award" />
          </div>
          <p className="EmptyState-text">{app.translator.trans('fof-badges.admin.no_badges')}</p>
          <Button className="Button Button--primary" icon="fas fa-magic" loading={this.installingDefaults} onclick={() => this.installDefaults()}>
            {app.translator.trans('fof-badges.admin.install_defaults')}
          </Button>
        </div>
      );
    }

    // Group badges by category using categoryId attribute (not relationship which can be cached)
    // Convert to string for comparison since categoryId may be number and cat.id() is string
    const uncategorized = this.badges.filter((b) => !b.categoryId());
    const byCategory = this.categories
      .filter((cat) => cat && typeof cat.id === 'function') // Ensure valid model
      .map((cat) => ({
        category: cat,
        badges: this.badges.filter((b) => String(b.categoryId()) === String(cat.id())),
      }))
      .filter((g) => g.badges.length > 0);

    return (
      <div className="BadgesList">
        {byCategory.map((group) => (
          <div className="BadgesList-group" key={group.category.id()}>
            <h3 className="BadgesList-groupTitle">{group.category.name()}</h3>
            <div className="CardList">
              <div className="CardList-header">
                <span>{app.translator.trans('fof-badges.admin.headers.order')}</span>
                <span></span>
                <span>{app.translator.trans('fof-badges.admin.headers.name')}</span>
                <span>{app.translator.trans('fof-badges.admin.headers.type')}</span>
                <span>{app.translator.trans('fof-badges.admin.headers.earned')}</span>
                <span>{app.translator.trans('fof-badges.admin.headers.rarity')}</span>
                <span>{app.translator.trans('fof-badges.admin.headers.status')}</span>
                <span></span>
              </div>
              {group.badges.map((badge, index) => this.badgeItem(badge, index, group.badges))}
            </div>
          </div>
        ))}
        {uncategorized.length > 0 && (
          <div className="BadgesList-group">
            <h3 className="BadgesList-groupTitle">{app.translator.trans('fof-badges.admin.uncategorized')}</h3>
            <div className="CardList">
              <div className="CardList-header">
                <span>{app.translator.trans('fof-badges.admin.headers.order')}</span>
                <span></span>
                <span>{app.translator.trans('fof-badges.admin.headers.name')}</span>
                <span>{app.translator.trans('fof-badges.admin.headers.type')}</span>
                <span>{app.translator.trans('fof-badges.admin.headers.earned')}</span>
                <span>{app.translator.trans('fof-badges.admin.headers.rarity')}</span>
                <span>{app.translator.trans('fof-badges.admin.headers.status')}</span>
                <span></span>
              </div>
              {uncategorized.map((badge, index) => this.badgeItem(badge, index, uncategorized))}
            </div>
          </div>
        )}
      </div>
    );
  }

  badgeItem(badge: Badge, index: number, categoryBadges: Badge[]): Mithril.Children {
    const isActive = badge.isActive();
    const isAutomatic = !!badge.triggerConfig();
    const isFirst = index === 0;
    const isLast = index === categoryBadges.length - 1;

    return (
      <div className={`CardList-item ${!isActive ? 'CardList-item--inactive' : ''}`} key={badge.id()}>
        {/* Order */}
        <div className="CardList-item-cell CardList-item-cell--order">
          <Button
            className="Button Button--icon Button--small"
            icon="fas fa-arrow-up"
            disabled={isFirst}
            onclick={() => this.moveBadge(badge, 'up')}
            aria-label={app.translator.trans('fof-badges.admin.move_up')}
          />
          <Button
            className="Button Button--icon Button--small"
            icon="fas fa-arrow-down"
            disabled={isLast}
            onclick={() => this.moveBadge(badge, 'down')}
            aria-label={app.translator.trans('fof-badges.admin.move_down')}
          />
        </div>

        {/* Icon */}
        <div className="CardList-item-cell" data-label={app.translator.trans('fof-badges.admin.headers.icon')}>
          <div
            className="BadgeIcon"
            style={{
              backgroundColor: badge.backgroundColor() || '#667eea',
              color: badge.iconColor() || '#fff',
            }}
          >
            <i className={badge.icon() || 'fas fa-award'} />
          </div>
        </div>

        {/* Name */}
        <div className="CardList-item-cell CardList-item-cell--primary" data-label={app.translator.trans('fof-badges.admin.headers.name')}>
          {badge.name()}
        </div>

        {/* Type */}
        <div className="CardList-item-cell" data-label={app.translator.trans('fof-badges.admin.headers.type')}>
          <span className={`TypeBadge TypeBadge--${isAutomatic ? 'automatic' : 'manual'}`}>
            {isAutomatic ? app.translator.trans('fof-badges.admin.automatic') : app.translator.trans('fof-badges.admin.manual')}
          </span>
        </div>

        {/* Earned Count */}
        <div className="CardList-item-cell CardList-item-cell--muted" data-label={app.translator.trans('fof-badges.admin.headers.earned')}>
          <i className="fas fa-users" /> {badge.earnedCount()}
        </div>

        {/* Rarity */}
        <div className="CardList-item-cell CardList-item-cell--muted" data-label={app.translator.trans('fof-badges.admin.headers.rarity')}>
          {(() => {
            const totalUsers = (app.forum.attribute('userCount') as number) || 1;
            const rarityInfo = getRarityInfo(badge.earnedCount(), totalUsers);
            return (
              <span style={{ color: rarityInfo.color }}>
                <i className="fas fa-gem" style={{ marginRight: '4px' }}></i>
                {rarityInfo.label}
              </span>
            );
          })()}
        </div>

        {/* Status */}
        <div className="CardList-item-cell" data-label={app.translator.trans('fof-badges.admin.headers.status')}>
          <span className={`StatusBadge StatusBadge--${isActive ? 'active' : 'inactive'}`}>
            {isActive ? app.translator.trans('fof-badges.admin.active') : app.translator.trans('fof-badges.admin.inactive')}
          </span>
        </div>

        {/* Actions */}
        <div className="CardList-item-actions">
          {!isAutomatic && isActive && (
            <Button className="Button" onclick={() => this.openAssignModal(badge)}>
              {app.translator.trans('fof-badges.admin.assign')}
            </Button>
          )}
          <Button className="Button Button--primary" onclick={() => this.openBadgeModal(badge)}>
            {app.translator.trans('fof-badges.admin.edit')}
          </Button>
          <Button className="Button Button--danger" onclick={() => this.deleteBadge(badge)}>
            {app.translator.trans('fof-badges.admin.delete')}
          </Button>
        </div>
      </div>
    );
  }

  categoriesList(): Mithril.Children {
    if (this.categories.length === 0) {
      return (
        <div className="EmptyState">
          <div className="EmptyState-icon">
            <i className="fas fa-folder" />
          </div>
          <p className="EmptyState-text">{app.translator.trans('fof-badges.admin.no_categories')}</p>
        </div>
      );
    }

    return (
      <div className="CategoriesList">
        <div className="CardList">
          <div className="CardList-header">
            <span>{app.translator.trans('fof-badges.admin.headers.order')}</span>
            <span>{app.translator.trans('fof-badges.admin.headers.name')}</span>
            <span>{app.translator.trans('fof-badges.admin.headers.description')}</span>
            <span>{app.translator.trans('fof-badges.admin.headers.badges')}</span>
            <span>{app.translator.trans('fof-badges.admin.headers.status')}</span>
            <span></span>
          </div>
          {this.categories.map((category, index) => this.categoryItem(category, index))}
        </div>
      </div>
    );
  }

  categoryItem(category: BadgeCategory, index: number): Mithril.Children {
    const isEnabled = category.isEnabled();
    const badgeCount = this.badges.filter((b) => String(b.categoryId()) === String(category.id())).length;
    const isFirst = index === 0;
    const isLast = index === this.categories.length - 1;

    return (
      <div className={`CardList-item ${!isEnabled ? 'CardList-item--inactive' : ''}`} key={category.id()}>
        {/* Order */}
        <div className="CardList-item-cell CardList-item-cell--order">
          <Button
            className="Button Button--icon Button--small"
            icon="fas fa-arrow-up"
            disabled={isFirst}
            onclick={() => this.moveCategory(category, 'up')}
            aria-label={app.translator.trans('fof-badges.admin.move_up')}
          />
          <Button
            className="Button Button--icon Button--small"
            icon="fas fa-arrow-down"
            disabled={isLast}
            onclick={() => this.moveCategory(category, 'down')}
            aria-label={app.translator.trans('fof-badges.admin.move_down')}
          />
        </div>

        {/* Name */}
        <div className="CardList-item-cell CardList-item-cell--primary" data-label={app.translator.trans('fof-badges.admin.headers.name')}>
          {category.name()}
        </div>

        {/* Description */}
        <div className="CardList-item-cell CardList-item-cell--muted" data-label={app.translator.trans('fof-badges.admin.headers.description')}>
          {category.description() || '-'}
        </div>

        {/* Badge Count */}
        <div className="CardList-item-cell" data-label={app.translator.trans('fof-badges.admin.headers.badges')}>
          <span className="CountBadge">{badgeCount}</span>
        </div>

        {/* Status */}
        <div className="CardList-item-cell" data-label={app.translator.trans('fof-badges.admin.headers.status')}>
          <span className={`StatusBadge StatusBadge--${isEnabled ? 'active' : 'inactive'}`}>
            {isEnabled ? app.translator.trans('fof-badges.admin.enabled') : app.translator.trans('fof-badges.admin.disabled')}
          </span>
        </div>

        {/* Actions */}
        <div className="CardList-item-actions">
          <Button className="Button Button--primary" onclick={() => this.openCategoryModal(category)}>
            {app.translator.trans('fof-badges.admin.edit')}
          </Button>
          <Button className="Button Button--danger" onclick={() => this.deleteCategory(category)}>
            {app.translator.trans('fof-badges.admin.delete')}
          </Button>
        </div>
      </div>
    );
  }

  openBadgeModal(badge?: Badge) {
    app.modal.show(BadgeEditModal, {
      badge,
      categories: this.categories,
      onSave: () => this.loadData(),
    });
  }

  openCategoryModal(category?: BadgeCategory) {
    app.modal.show(CategoryEditModal, {
      category,
      onSave: () => this.loadData(),
    });
  }

  openAssignModal(badge: Badge) {
    app.modal.show(ManualAssignModal, {
      badge,
      onAssign: () => this.loadData(),
    });
  }

  async deleteBadge(badge: Badge) {
    if (
      !confirm(
        extractText(
          app.translator.trans('fof-badges.admin.delete_badge_confirm', {
            name: badge.name(),
          })
        )
      )
    ) {
      return;
    }

    try {
      await badge.delete();
      this.loadData();
    } catch (error) {
      console.error('Failed to delete badge:', error);
    }
  }

  async deleteCategory(category: BadgeCategory) {
    const badgeCount = this.badges.filter((b) => String(b.categoryId()) === String(category.id())).length;

    if (badgeCount > 0) {
      if (
        !confirm(
          extractText(
            app.translator.trans('fof-badges.admin.delete_category_confirm_with_badges', {
              name: category.name(),
              count: badgeCount,
            })
          )
        )
      ) {
        return;
      }
    } else {
      if (
        !confirm(
          extractText(
            app.translator.trans('fof-badges.admin.delete_category_confirm', {
              name: category.name(),
            })
          )
        )
      ) {
        return;
      }
    }

    try {
      await category.delete();
      this.loadData();
    } catch (error) {
      console.error('Failed to delete category:', error);
    }
  }

  async moveCategory(category: BadgeCategory, direction: 'up' | 'down') {
    const index = this.categories.indexOf(category);
    if (index === -1) return;

    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= this.categories.length) return;

    // Swap positions in array
    const temp = this.categories[index];
    this.categories[index] = this.categories[newIndex];
    this.categories[newIndex] = temp;
    m.redraw();

    // Build order array and save
    try {
      const order = this.categories.map((c, i) => ({ id: c.id(), order: i }));
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/badge-categories/order',
        body: { order },
      });
    } catch (error) {
      console.error('Failed to reorder category:', error);
      await this.loadData(); // Reload only on error to restore correct state
    }
  }

  async moveBadge(badge: Badge, direction: 'up' | 'down') {
    // Get badges in the same category using categoryId attribute
    const categoryId = badge.categoryId();
    const categoryBadges = this.badges.filter((b) => {
      if (categoryId) {
        return String(b.categoryId()) === String(categoryId);
      }
      return !b.categoryId();
    });

    const index = categoryBadges.indexOf(badge);
    if (index === -1) return;

    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= categoryBadges.length) return;

    // Swap in the main badges array (find actual indices)
    const mainIndex = this.badges.indexOf(badge);
    const swapBadge = categoryBadges[newIndex];
    const mainSwapIndex = this.badges.indexOf(swapBadge);

    if (mainIndex !== -1 && mainSwapIndex !== -1) {
      const temp = this.badges[mainIndex];
      this.badges[mainIndex] = this.badges[mainSwapIndex];
      this.badges[mainSwapIndex] = temp;
    }
    m.redraw();

    // Build order array for badges in this category and save
    // Re-filter after swap to get correct order
    const updatedCategoryBadges = this.badges.filter((b) => {
      if (categoryId) {
        return String(b.categoryId()) === String(categoryId);
      }
      return !b.categoryId();
    });

    try {
      const order = updatedCategoryBadges.map((b, i) => ({ id: b.id(), order: i }));
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/badges/order',
        body: { order },
      });
    } catch (error) {
      console.error('Failed to reorder badge:', error);
      await this.loadData(); // Reload only on error to restore correct state
    }
  }

  async installDefaults() {
    this.installingDefaults = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/badges/install-defaults',
      });

      app.alerts.show({ type: 'success' }, app.translator.trans('fof-badges.admin.install_defaults_success'));
      await this.loadData();
    } catch (error) {
      console.error('Failed to install default badges:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('fof-badges.admin.install_defaults_error'));
    } finally {
      this.installingDefaults = false;
      m.redraw();
    }
  }
}
