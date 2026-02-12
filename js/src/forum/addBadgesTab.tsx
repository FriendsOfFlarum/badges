import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';

export default function addBadgesTab() {
  extend(UserPage.prototype, 'navItems', function (items) {
    const user = (this as any).user;
    if (!user) return;

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
}
