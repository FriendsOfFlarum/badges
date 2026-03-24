import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import username from 'flarum/common/helpers/username';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';
import type { Badge, BadgeCategory, UserBadge } from '../../common';
import getRarityInfo, { formatPercent } from '../../common/helpers/getRarityInfo';

interface BadgeModalAttrs extends IInternalModalAttrs {
  badge: Badge;
  onhide?: () => void;
}

export default class BadgeModal extends Modal<BadgeModalAttrs> {
  badge!: Badge;
  loading: boolean = true;
  earnedUsers: UserBadge[] = [];

  oninit(vnode: Mithril.Vnode<BadgeModalAttrs>) {
    super.oninit(vnode);
    this.badge = this.attrs.badge;
    this.loadEarnedUsers();
  }

  async loadEarnedUsers(): Promise<void> {
    this.loading = true;
    m.redraw();

    try {
      const userBadges = await app.store.find<UserBadge[]>('user-badges', {
        filter: { badge: String(this.badge.id()) },
        include: 'user',
        sort: '-earnedAt',
        page: { limit: 20 },
      });
      this.earnedUsers = userBadges as UserBadge[];
    } catch (error) {
      console.error('Failed to load earned users:', error);
      this.earnedUsers = [];
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  className(): string {
    return 'BadgeModal';
  }

  title(): Mithril.Children {
    return this.badge.name();
  }

  content(): Mithril.Children {
    const totalUsers = (app.forum.attribute('userCount') as number) || 1;
    const rarityInfo = getRarityInfo(this.badge.earnedCount(), totalUsers);
    const percentFormatted = formatPercent(rarityInfo.percent);

    return (
      <div className="Modal-body">
        {/* Badge Header */}
        <div className="BadgeModal-header">
          <div
            className="BadgeModal-icon"
            style={{
              backgroundColor: this.badge.backgroundColor(),
              color: this.badge.iconColor(),
            }}
          >
            <i className={this.badge.icon()}></i>
          </div>
          <div className="BadgeModal-info">
            <h2 className="BadgeModal-name">{this.badge.name()}</h2>
            {this.badge.description() && <p className="BadgeModal-description">{this.badge.description()}</p>}
            {this.badge.category() && (
              <span className="BadgeModal-category">
                <i className="fas fa-folder"></i>
                {(this.badge.category() as BadgeCategory).name()}
              </span>
            )}
          </div>
        </div>
        {/* Rarity Stats */}
        <div className="BadgeModal-stats">
          <div className="BadgeModal-rarity">
            <div className="BadgeModal-rarityInfo">
              <span className={`BadgeModal-rarityTier BadgeModal-rarityTier--${rarityInfo.tier}`} style={{ color: rarityInfo.color }}>
                <i className="fas fa-gem"></i>
                {rarityInfo.label}
              </span>
              <span className="BadgeModal-earnedCount">
                <i className="fas fa-users"></i>
                {app.translator.trans('fof-badges.forum.earned_by', {
                  count: this.badge.earnedCount(),
                })}
              </span>
            </div>
            <p className="BadgeModal-rarityExplain">
              {app.translator.trans('fof-badges.lib.rarity_explain', {
                percent: percentFormatted,
              })}
            </p>
          </div>
        </div>
        {/* Earned Users List */}
        <div className="BadgeModal-users">
          <h3 className="BadgeModal-usersTitle">
            <i className="fas fa-trophy"></i>
            {app.translator.trans('fof-badges.forum.earned_users')}
          </h3>

          {this.loading ? (
            <div className="BadgeModal-loading">
              <LoadingIndicator />
            </div>
          ) : this.earnedUsers.length === 0 ? (
            <div className="BadgeModal-noUsers">
              <i className="fas fa-user-slash"></i>
              <p>{app.translator.trans('fof-badges.forum.no_users_earned')}</p>
            </div>
          ) : (
            <ul className="BadgeModal-userList">
              {this.earnedUsers.map((ub) => {
                const user = ub.user();
                if (!user) return null;

                return (
                  <li className="BadgeModal-userItem" key={ub.id()}>
                    <Link href={app.route('user', { username: user.username() })} className="BadgeModal-userLink">
                      <Avatar user={user} />
                      <span className="BadgeModal-userName">{username(user)}</span>
                    </Link>
                    <span className="BadgeModal-earnedDate">{humanTime(ub.earnedAt())}</span>
                  </li>
                );
              })}
            </ul>
          )}

          {this.earnedUsers.length === 20 && (
            <div className="BadgeModal-moreUsers">
              <p>{app.translator.trans('fof-badges.forum.more_users_earned')}</p>
            </div>
          )}
        </div>
      </div>
    );
  }

  onremove(vnode: Mithril.VnodeDOM<BadgeModalAttrs>): void {
    super.onremove(vnode);
    this.attrs.onhide?.();
  }
}
