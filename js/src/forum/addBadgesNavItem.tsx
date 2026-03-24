import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';

export default function addBadgesNavItem() {
  extend(IndexSidebar.prototype, 'navItems', function (items) {
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
}
