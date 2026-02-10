import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import UserPage from 'flarum/forum/components/UserPage';
import PostUser from 'flarum/forum/components/PostUser';
import UserCard from 'flarum/forum/components/UserCard';
import CommentPost from 'flarum/forum/components/CommentPost';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import { Badge, BadgeCategory, UserBadge } from '../common';
import BadgesPage from './components/BadgesPage';
import UserBadgesPage from './components/UserBadgesPage';
import BadgeNotification from './components/BadgeNotification';

app.initializers.add('fof-badges', () => {
  // Register models
  app.store.models['badges'] = Badge;
  app.store.models['badge-categories'] = BadgeCategory;
  app.store.models['user-badges'] = UserBadge;

  // Register routes
  app.routes['badges'] = { path: '/badges', component: BadgesPage };
  app.routes['user.badges'] = { path: '/u/:username/badges', component: UserBadgesPage };

  // Add link to forum navigation
  extend(IndexPage.prototype, 'navItems', function (items) {
    if (!app.forum.attribute('canViewBadges')) {
      return;
    }

    items.add(
      'badges',
      <LinkButton href={app.route('badges')} icon="fas fa-award">
        {app.translator.trans('fof-badges.forum.nav.badges')}
      </LinkButton>,
      50
    );
  });

  // Add badges tab to user profile
  extend(UserPage.prototype, 'navItems', function (items) {
    const user = (this as any).user;
    if (!user) return;

    // Check if current user can view this user's badges
    if (!app.forum.attribute('canViewUserBadges')) {
      return;
    }

    const badgeCount = user.attribute('badgeCount') || 0;

    items.add(
      'badges',
      <LinkButton href={app.route('user.badges', { username: user.username() })} icon="fas fa-award">
        {app.translator.trans('fof-badges.forum.user.badges_tab')}
        {badgeCount > 0 && <span className="Button-badge">{badgeCount}</span>}
      </LinkButton>,
      80
    );
  });

  // Register notification type
  app.notificationComponents.badgeEarned = BadgeNotification;

  // Helper to get visible badges array from user attributes
  const getVisibleBadges = (user: any): Array<{ name: string; icon: string }> => {
    return (user.attribute('visibleBadges') as Array<{ name: string; icon: string }>) || [];
  };

  // Add primary badge next to username in posts
  extend(PostUser.prototype, 'view', function (vnode) {
    const post = (this as any).attrs.post;
    const user = post.user();

    if (!user) return;

    const displayMode = app.forum.attribute('primaryBadgeDisplay') as string;
    if (displayMode === 'hidden') return;

    const badgeName = user.attribute('primaryBadgeName') as string | null;
    const badgeIcon = user.attribute('primaryBadgeIcon') as string | null;

    if (!badgeName || !badgeIcon) return;

    if (vnode && vnode.children && Array.isArray(vnode.children)) {
      const badgeElement = (
        <Link href={app.route('user.badges', { username: user.username() })} className="PrimaryBadge" title={badgeName}>
          <i className={badgeIcon}></i>
          {displayMode === 'icon_name' && <span className="PrimaryBadge-name">{badgeName}</span>}
        </Link>
      );

      vnode.children.push(badgeElement);
    }
  });

  // Add badges to user card (profile page) - pill style with first badge name
  extend(UserCard.prototype, 'infoItems', function (items) {
    const user = (this as any).attrs.user;
    if (!user) return;

    // Check if user card badges are enabled
    if (app.forum.attribute('showBadgesOnUserCard') === false) return;

    const badgeCount = (user.attribute('badgeCount') as number) || 0;
    if (badgeCount === 0) return;

    // Use primaryBadge (favorite or rarest) for user card - independent of post limit
    const badgeName = user.attribute('primaryBadgeName') as string | null;
    const badgeIcon = user.attribute('primaryBadgeIcon') as string | null;
    if (!badgeName || !badgeIcon) return;

    const remainingCount = badgeCount - 1;

    items.add(
      'cardBadges',
      <Link href={app.route('user.badges', { username: user.username() })} className="UserCardBadge">
        <i className={badgeIcon}></i>
        <span className="UserCardBadge-name">{badgeName}</span>
        {remainingCount > 0 && <span className="UserCardBadge-more">+{remainingCount}</span>}
      </Link>,
      15
    );
  });

  // Add badges to post footer
  extend(CommentPost.prototype, 'footerItems', function (items) {
    const post = (this as any).attrs.post;
    const user = post.user();

    if (!user) return;
    if (post.isHidden()) return;

    const badgeCount = (user.attribute('badgeCount') as number) || 0;
    if (badgeCount === 0) return;

    const visibleBadges = getVisibleBadges(user);
    if (visibleBadges.length === 0) return;

    const remainingCount = badgeCount - visibleBadges.length;

    items.add(
      'badges',
      <Link href={app.route('user.badges', { username: user.username() })} className="PostBadges">
        {visibleBadges.map((badge, index) => (
          <span className="PostBadges-item" title={badge.name} key={index}>
            <i className={badge.icon}></i>
          </span>
        ))}
        {remainingCount > 0 && <span className="PostBadges-more">+{remainingCount}</span>}
      </Link>,
      -10
    );
  });
});
