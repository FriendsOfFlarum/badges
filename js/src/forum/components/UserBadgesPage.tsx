import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { UserBadge } from '../../common';
import BadgeCard from './BadgeCard';
import BadgeModal from './BadgeModal';
import AssignBadgeModal from './AssignBadgeModal';
import UserRecalculateModal from './UserRecalculateModal';

export default class UserBadgesPage extends UserPage {
  loading: boolean = true;
  loadingMore: boolean = false;
  userBadges: UserBadge[] = [];
  totalBadgeCount: number = 0;
  hasMoreBadges: boolean = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  show(user: User): void {
    super.show(user);

    this.loadUserBadges();
  }

  async loadUserBadges(loadMore: boolean = false): Promise<void> {
    if (!this.user) return;

    if (loadMore) {
      this.loadingMore = true;
    } else {
      this.loading = true;
      this.userBadges = [];
    }
    m.redraw();

    try {
      const offset = loadMore ? this.userBadges.length : 0;
      const response = await app.request<any>({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/user-badges',
        params: {
          'filter[user]': this.user.id(),
          include: 'badge,badge.category',
          sort: '-earnedAt',
          'page[offset]': offset,
          'page[limit]': 20,
        },
      });

      // Store includes in the store first
      if (response.included) {
        response.included.forEach((inc: any) => {
          app.store.pushObject(inc);
        });
      }

      // Parse JSON:API response
      const newBadges = response.data.map((data: any) => app.store.pushObject(data)) as UserBadge[];

      if (loadMore) {
        this.userBadges = [...this.userBadges, ...newBadges];
      } else {
        this.userBadges = newBadges;
      }

      // Get total from meta
      if (response.meta?.total !== undefined) {
        this.totalBadgeCount = response.meta.total;
      }

      // Check if there are more badges to load
      this.hasMoreBadges = this.userBadges.length < this.totalBadgeCount;
    } catch (error) {
      console.error('Failed to load user badges:', error);
      if (!loadMore) {
        this.userBadges = [];
        this.totalBadgeCount = 0;
      }
    } finally {
      this.loading = false;
      this.loadingMore = false;
      m.redraw();
    }
  }

  content(): Mithril.Children {
    if (this.loading) {
      return (
        <div className="UserBadgesPage UserBadgesPage--loading">
          <LoadingIndicator />
        </div>
      );
    }

    const canModerate = app.forum.attribute('canGiveBadges') || app.forum.attribute('canModerateBadges');

    if (this.userBadges.length === 0) {
      return (
        <div className="UserBadgesPage">
          {canModerate && this.user && (
            <div className="UserBadgesPage-header">
              <h2>{app.translator.trans('fof-badges.forum.user.badges_count', { count: 0 })}</h2>
              {this.renderRecalculateButton()}
            </div>
          )}
          <div className="UserBadgesPage-emptyState">
            <i className="fas fa-award UserBadgesPage-emptyIcon"></i>
            <p>{app.translator.trans('fof-badges.forum.user.no_badges')}</p>
          </div>
        </div>
      );
    }

    return (
      <div className="UserBadgesPage">
        <div className="UserBadgesPage-header">
          <h2>
            {app.translator.trans('fof-badges.forum.user.badges_count', {
              count: this.totalBadgeCount,
            })}
          </h2>
          {canModerate && this.user && this.renderRecalculateButton()}
        </div>
        <div className={`UserBadgesPage-grid ${app.forum.attribute('badgeStyle') === 'tags' ? 'Badges--style-tags' : ''}`}>
          {this.userBadges.map((ub) => {
            const badge = ub.badge();
            if (!badge) return null;

            const showNewTag = this.isRecentBadge(ub.earnedAt()) && app.forum.attribute('badgeNewHighlightEnabled');
            const isOwner = app.session.user && this.user && app.session.user.id() === this.user.id();
            const canRevoke = canModerate && ub.grantedBy() === 'manual';
            const showActions = isOwner || canRevoke;

            return (
              <div className="UserBadgesPage-item" key={ub.id()}>
                <div className="UserBadgesPage-badgeWrapper">
                  <BadgeCard
                    badge={badge}
                    onclick={() => this.showBadgeModal(badge)}
                    earnedAt={ub.earnedAt()}
                    isFavorite={ub.isPrimary()}
                    isHidden={!ub.showOnCard()}
                    isNew={showNewTag}
                    isManual={ub.grantedBy() === 'manual'}
                    isOwner={isOwner}
                    reason={ub.reason()}
                  />
                </div>
                {showActions && (
                  <Dropdown
                    className="UserBadgesPage-actions"
                    buttonClassName="Button Button--icon Button--flat"
                    icon="fas fa-ellipsis-v"
                    accessibleToggleLabel={app.translator.trans('fof-badges.forum.user.badge_actions')}
                  >
                    {isOwner && (
                      <Button className="hasIcon" icon={ub.isPrimary() ? 'fas fa-star' : 'far fa-star'} onclick={() => this.toggleFavorite(ub)}>
                        {ub.isPrimary()
                          ? app.translator.trans('fof-badges.forum.user.remove_favorite')
                          : app.translator.trans('fof-badges.forum.user.set_favorite')}
                      </Button>
                    )}
                    {isOwner && !ub.isPrimary() && (
                      <Button
                        className="hasIcon"
                        icon={ub.showOnCard() ? 'fas fa-eye-slash' : 'fas fa-eye'}
                        onclick={() => this.toggleVisibility(ub)}
                      >
                        {ub.showOnCard()
                          ? app.translator.trans('fof-badges.forum.user.hide_badge')
                          : app.translator.trans('fof-badges.forum.user.show_badge')}
                      </Button>
                    )}
                    {canRevoke && (
                      <Button className="hasIcon" icon="fas fa-trash" onclick={() => this.revokeBadge(ub, badge.name())}>
                        {app.translator.trans('fof-badges.forum.user.revoke_badge')}
                      </Button>
                    )}
                  </Dropdown>
                )}
              </div>
            );
          })}
        </div>
        {this.hasMoreBadges && (
          <div className="UserBadgesPage-loadMore">
            <Button className="Button" loading={this.loadingMore} onclick={() => this.loadUserBadges(true)}>
              {app.translator.trans('fof-badges.forum.user.load_more')}
            </Button>
          </div>
        )}
      </div>
    );
  }

  renderRecalculateButton(): Mithril.Children {
    return (
      <div className="UserBadgesPage-moderatorActions">
        <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.showAssignModal()}>
          {app.translator.trans('fof-badges.forum.user.assign_badge')}
        </Button>
        <Button className="Button" icon="fas fa-sync-alt" onclick={() => this.showRecalculateModal()}>
          {app.translator.trans('fof-badges.forum.user.recalculate')}
        </Button>
      </div>
    );
  }

  showAssignModal(): void {
    if (!this.user) return;

    app.modal.show(AssignBadgeModal, {
      user: this.user,
      onAssign: () => {
        this.loadUserBadges();
      },
    });
  }

  showRecalculateModal(): void {
    if (!this.user) return;

    app.modal.show(UserRecalculateModal, {
      user: this.user,
      onRecalculate: () => {
        this.loadUserBadges();
      },
    });
  }

  showBadgeModal(badge: any): void {
    app.modal.show(BadgeModal, { badge });
  }

  /**
   * Check if badge was earned within the last 7 days
   */
  isRecentBadge(earnedAt: Date | string | null): boolean {
    if (!earnedAt) return false;
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
    return new Date(earnedAt) > sevenDaysAgo;
  }

  async toggleFavorite(userBadge: UserBadge): Promise<void> {
    try {
      const response = await app.request<{ success: boolean; is_primary: boolean; show_on_card: boolean }>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/user-badges/${userBadge.id()}/toggle`,
        body: { action: 'toggleFavorite' },
      });

      if (response.success) {
        // Update local state - if this badge is now primary, unset others
        if (response.is_primary) {
          this.userBadges.forEach((ub) => {
            if (ub.id() !== userBadge.id()) {
              ub.pushAttributes({ isPrimary: false });
            }
          });
        }
        userBadge.pushAttributes({ isPrimary: response.is_primary, showOnCard: response.show_on_card });
        m.redraw();
      }
    } catch (error) {
      console.error('Failed to toggle favorite:', error);
    }
  }

  async toggleVisibility(userBadge: UserBadge): Promise<void> {
    try {
      const response = await app.request<{ success: boolean; show_on_card: boolean }>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/user-badges/${userBadge.id()}/toggle`,
        body: { action: 'toggleVisibility' },
      });

      if (response.success) {
        userBadge.pushAttributes({ showOnCard: response.show_on_card });
        m.redraw();
      }
    } catch (error) {
      console.error('Failed to toggle visibility:', error);
    }
  }

  async revokeBadge(userBadge: UserBadge, badgeName: string): Promise<void> {
    if (!confirm(extractText(app.translator.trans('fof-badges.forum.user.revoke_confirm', { badge: badgeName })))) {
      return;
    }

    try {
      await app.request({
        method: 'DELETE',
        url: `${app.forum.attribute('apiUrl')}/user-badges/${userBadge.id()}`,
      });

      // Remove from local list
      this.userBadges = this.userBadges.filter((ub) => ub.id() !== userBadge.id());

      app.alerts.show({ type: 'success' }, app.translator.trans('fof-badges.forum.user.badge_revoked', { badge: badgeName }));

      m.redraw();
    } catch (error) {
      console.error('Failed to revoke badge:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('fof-badges.forum.user.revoke_error'));
    }
  }
}
