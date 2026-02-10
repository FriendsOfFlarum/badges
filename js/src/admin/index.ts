import app from 'flarum/admin/app';
import { Badge, BadgeCategory, UserBadge } from '../common';
import BadgesPage from './components/BadgesPage';

app.initializers.add('fof-badges', () => {
  // Register models
  app.store.models['badges'] = Badge;
  app.store.models['badge-categories'] = BadgeCategory;
  app.store.models['user-badges'] = UserBadge;

  // Register admin page
  app.extensionData
    .for('fof-badges')
    .registerPage(BadgesPage)
    .registerPermission(
      {
        icon: 'fas fa-award',
        label: app.translator.trans('fof-badges.admin.permissions.moderate'),
        permission: 'badges.moderate',
      },
      'moderate'
    )
    .registerPermission(
      {
        icon: 'fas fa-hand-holding',
        label: app.translator.trans('fof-badges.admin.permissions.give_manually'),
        permission: 'badges.giveManually',
      },
      'moderate'
    )
    .registerPermission(
      {
        icon: 'fas fa-eye',
        label: app.translator.trans('fof-badges.admin.permissions.view_list'),
        permission: 'badges.viewList',
        allowGuest: true,
      },
      'view'
    )
    .registerPermission(
      {
        icon: 'fas fa-id-badge',
        label: app.translator.trans('fof-badges.admin.permissions.view_user_badges'),
        permission: 'badges.viewUserBadges',
        allowGuest: true,
      },
      'view'
    );
});
