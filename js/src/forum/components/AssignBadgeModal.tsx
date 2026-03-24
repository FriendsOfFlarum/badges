import Form from 'flarum/common/components/Form';
import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { Badge, BadgeCategory, UserBadge } from '../../common';

interface AssignBadgeModalAttrs extends IInternalModalAttrs {
  user: User;
  onAssign?: () => void;
}

export default class AssignBadgeModal extends Modal<AssignBadgeModalAttrs> {
  user!: User;
  badges: Badge[] = [];
  categories: BadgeCategory[] = [];
  userBadgeIds: Set<string> = new Set();
  loading: boolean = true;
  saving: boolean = false;
  selectedBadgeId: string | null = null;
  reason: string = '';

  oninit(vnode: Mithril.Vnode<AssignBadgeModalAttrs>) {
    super.oninit(vnode);
    this.user = this.attrs.user;
    this.loadBadges();
  }

  async loadBadges(): Promise<void> {
    this.loading = true;
    m.redraw();

    try {
      const [badges, categories, userBadges] = await Promise.all([
        app.store.find<Badge[]>('badges', {
          include: 'category',
        }),
        app.store.find<BadgeCategory[]>('badge-categories'),
        app.store.find<UserBadge[]>('user-badges', {
          filter: { user: String(this.user.id()) },
        }),
      ]);

      // Get the badge IDs that this user already has
      this.userBadgeIds = new Set(
        (userBadges as UserBadge[]).filter((ub) => ub && typeof ub.badge === 'function' && ub.badge()).map((ub) => (ub.badge() as Badge).id()!)
      );

      // Filter only active manual badges (no trigger_config or empty conditions)
      // and exclude badges the user already has
      this.badges = (badges as Badge[]).filter((b) => {
        if (!b || typeof b.id !== 'function' || !b.isActive()) return false;
        // Exclude badges user already has
        if (this.userBadgeIds.has(b.id()!)) return false;
        const triggerConfig = b.triggerConfig();
        // Manual badge = no trigger config or no conditions
        return !triggerConfig || !triggerConfig.conditions || triggerConfig.conditions.length === 0;
      });
      this.categories = (categories as BadgeCategory[]).filter((c) => c && typeof c.id === 'function' && c.isEnabled());
    } catch (error) {
      console.error('Failed to load badges:', error);
      this.badges = [];
      this.categories = [];
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  className(): string {
    return 'AssignBadgeModal';
  }

  title(): Mithril.Children {
    return app.translator.trans('fof-badges.forum.user.assign_badge_title', {
      username: this.user.displayName(),
    });
  }

  content(): Mithril.Children {
    if (this.loading) {
      return (
        <div className="Modal-body">
          <div className="AssignBadgeModal-loading">
            <LoadingIndicator />
          </div>
        </div>
      );
    }

    if (this.badges.length === 0) {
      return (
        <div className="Modal-body">
          <div className="AssignBadgeModal-empty">
            <i className="fas fa-award"></i>
            <p>{app.translator.trans('fof-badges.forum.user.no_badges_available')}</p>
          </div>
        </div>
      );
    }

    // Build badge options grouped by category
    const options: { [key: string]: string } = {
      '': app.translator.trans('fof-badges.forum.user.select_badge') as string,
    };

    // Sort badges by category
    const uncategorized: Badge[] = [];
    const categorized: Map<string, Badge[]> = new Map();

    this.badges.forEach((badge) => {
      const category = badge.category();
      if (category && typeof category.id === 'function') {
        const catId = category.id()!;
        if (!categorized.has(catId)) {
          categorized.set(catId, []);
        }
        categorized.get(catId)!.push(badge);
      } else {
        uncategorized.push(badge);
      }
    });

    // Add categorized badges
    this.categories.forEach((category) => {
      const catBadges = categorized.get(category.id()!);
      if (catBadges && catBadges.length > 0) {
        catBadges.forEach((badge) => {
          options[badge.id()!] = `${category.name()} > ${badge.name()}`;
        });
      }
    });

    // Add uncategorized badges
    uncategorized.forEach((badge) => {
      options[badge.id()!] = badge.name();
    });

    return (
      <div className="Modal-body">
        <Form>
          {}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.forum.user.select_badge_label')}</label>
            <Select
              options={options}
              value={this.selectedBadgeId || ''}
              onchange={(value: string) => {
                this.selectedBadgeId = value || null;
              }}
              className="FormControl"
            />
          </div>
          {}
          <div className="Form-group">
            <label>{app.translator.trans('fof-badges.forum.user.assign_reason')}</label>
            <textarea
              className="FormControl"
              value={this.reason}
              oninput={(e: InputEvent) => {
                this.reason = (e.target as HTMLTextAreaElement).value;
              }}
              rows={3}
              placeholder={app.translator.trans('fof-badges.forum.user.assign_reason_placeholder')}
            />
            <p className="helpText">{app.translator.trans('fof-badges.forum.user.assign_reason_help')}</p>
          </div>
          {}
          <div className="Form-group AssignBadgeModal-buttons">
            <Button className="Button Button--primary" loading={this.saving} disabled={!this.selectedBadgeId} onclick={() => this.assign()}>
              {app.translator.trans('fof-badges.forum.user.assign_button')}
            </Button>
            <Button className="Button" onclick={() => this.hide()}>
              {app.translator.trans('fof-badges.forum.user.cancel')}
            </Button>
          </div>
        </Form>
      </div>
    );
  }

  async assign(): Promise<void> {
    if (!this.selectedBadgeId) return;

    const selectedBadge = this.badges.find((b) => b.id() === this.selectedBadgeId);
    if (!selectedBadge) return;

    const userName = this.user.displayName();
    const badgeName = selectedBadge.name();

    this.saving = true;
    m.redraw();

    try {
      await app.store.createRecord('user-badges').save({
        userId: this.user.id(),
        badgeId: this.selectedBadgeId,
        reason: this.reason || null,
      });

      app.alerts.show(
        { type: 'success' },
        app.translator.trans('fof-badges.forum.user.badge_assigned', {
          badge: badgeName,
          username: userName,
        })
      );

      this.attrs.onAssign?.();
      this.hide();
    } catch (error) {
      // Error will be handled by Flarum's default error handler
      throw error;
    } finally {
      this.saving = false;
      m.redraw();
    }
  }
}
