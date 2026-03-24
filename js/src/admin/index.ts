import app from 'flarum/admin/app';
import BadgesPage from './components/BadgesPage';

export { default as extend } from './extend';

app.initializers.add('fof-badges', () => {
  app.registry
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
