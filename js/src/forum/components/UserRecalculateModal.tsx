import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { Badge } from '../../common';

interface UserRecalculateModalAttrs extends IInternalModalAttrs {
  user: User;
  onRecalculate?: () => void;
}

export default class UserRecalculateModal extends Modal<UserRecalculateModalAttrs> {
  loading: boolean = true;
  recalculating: boolean = false;
  badges: Badge[] = [];

  // Form state
  selectedBadgeId: string = '';
  noRevoke: boolean = true;
  reapplyActions: boolean = false;

  oninit(vnode: Mithril.Vnode<UserRecalculateModalAttrs>) {
    super.oninit(vnode);
    this.loadBadges();
  }

  className(): string {
    return 'UserRecalculateModal Modal--small';
  }

  title(): Mithril.Children {
    return app.translator.trans('fof-badges.forum.user.recalculate_modal.title');
  }

  async loadBadges(): Promise<void> {
    this.loading = true;
    m.redraw();

    try {
      const badges = await app.store.find<Badge[]>('badges');
      // Filter to only automatic badges (with trigger_config) that are active
      this.badges = (Array.isArray(badges) ? badges : []).filter((b) => {
        if (!b || typeof b.isActive !== 'function') return false;
        if (!b.isActive()) return false;

        // Include automatic badges
        const hasConfig = b.triggerConfig();
        if (hasConfig) return true;

        // Include manual badges with actions (for re-apply)
        const actions = b.actions();
        return actions && (actions.send_notification || actions.add_to_group);
      });
    } catch (error) {
      console.error('Failed to load badges:', error);
      this.badges = [];
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  content(): Mithril.Children {
    if (this.loading) {
      return (
        <div className="Modal-body">
          <div className="Form">
            <p>{app.translator.trans('fof-badges.forum.loading')}</p>
          </div>
        </div>
      );
    }

    const automaticBadges = this.badges.filter((b) => b.triggerConfig());
    const manualBadgesWithActions = this.badges.filter((b) => !b.triggerConfig());

    // Build badge options
    const badgeOptions: Record<string, string> = {
      '': app.translator.trans('fof-badges.forum.user.recalculate_modal.all_badges') as string,
    };

    automaticBadges.forEach((badge) => {
      badgeOptions[badge.id() as string] = badge.name();
    });

    manualBadgesWithActions.forEach((badge) => {
      badgeOptions[badge.id() as string] = `${badge.name()} (${app.translator.trans('fof-badges.forum.user.recalculate_modal.manual')})`;
    });

    return (
      <div className="Modal-body">
        <div className="Form">
          {/* Badge Selection */}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.forum.user.recalculate_modal.badge_label')}</label>
            {Select.component({
              options: badgeOptions,
              value: this.selectedBadgeId,
              onchange: (value: string) => {
                this.selectedBadgeId = value;
              },
              disabled: this.recalculating,
            })}
          </div>

          {/* No Revoke Option */}
          <div className="Form-group">
            {Switch.component(
              {
                state: this.noRevoke,
                onchange: (value: boolean) => {
                  this.noRevoke = value;
                },
                disabled: this.recalculating,
              },
              app.translator.trans('fof-badges.forum.user.recalculate_modal.no_revoke_label')
            )}
            <p className="helpText">{app.translator.trans('fof-badges.forum.user.recalculate_modal.no_revoke_help')}</p>
          </div>

          {/* Re-apply Actions Option */}
          <div className="Form-group">
            {Switch.component(
              {
                state: this.reapplyActions,
                onchange: (value: boolean) => {
                  this.reapplyActions = value;
                },
                disabled: this.recalculating,
              },
              app.translator.trans('fof-badges.forum.user.recalculate_modal.reapply_actions_label')
            )}
            <p className="helpText">{app.translator.trans('fof-badges.forum.user.recalculate_modal.reapply_actions_help')}</p>
          </div>

          {/* Submit Button */}
          <div className="Form-group">
            <Button
              className="Button Button--primary Button--block"
              loading={this.recalculating}
              disabled={automaticBadges.length === 0 && manualBadgesWithActions.length === 0}
              onclick={() => this.submit()}
            >
              {app.translator.trans('fof-badges.forum.user.recalculate_modal.submit')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  async submit(): Promise<void> {
    this.recalculating = true;
    m.redraw();

    try {
      const body: Record<string, unknown> = {
        userId: this.attrs.user.id(),
        noRevoke: this.noRevoke,
        reapplyActions: this.reapplyActions,
      };

      if (this.selectedBadgeId) {
        body.badgeId = this.selectedBadgeId;
      }

      const response = await app.request<{
        success: boolean;
        awarded: number;
        revoked: number;
        reapplied: number;
        skipped: number;
      }>({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/badges/recalculate',
        body,
      });

      if (response.success) {
        // Show different message based on what happened
        if (response.reapplied > 0 && response.awarded === 0 && response.revoked === 0) {
          app.alerts.show(
            { type: 'success' },
            app.translator.trans('fof-badges.forum.user.recalculate_reapplied', {
              reapplied: response.reapplied,
            })
          );
        } else {
          app.alerts.show(
            { type: 'success' },
            app.translator.trans('fof-badges.forum.user.recalculate_success', {
              awarded: response.awarded,
              revoked: response.revoked,
              reapplied: response.reapplied,
            })
          );
        }

        this.attrs.onRecalculate?.();
        this.hide();
      }
    } catch (error) {
      console.error('Failed to recalculate badges:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('fof-badges.forum.user.recalculate_error'));
    } finally {
      this.recalculating = false;
      m.redraw();
    }
  }
}
