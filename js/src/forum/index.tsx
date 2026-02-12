import app from 'flarum/forum/app';
import BadgeNotification from './components/BadgeNotification';
import addBadgesNavItem from './addBadgesNavItem';
import addBadgesTab from './addBadgesTab';
import addPrimaryBadgeToPost from './addPrimaryBadgeToPost';
import addBadgesToUserCard from './addBadgesToUserCard';
import addBadgesToPostFooter from './addBadgesToPostFooter';

export { default as extend } from './extend';

app.initializers.add('fof-badges', () => {
  // Register notification type
  app.notificationComponents.badgeEarned = BadgeNotification;

  // UI extensions
  addBadgesNavItem();
  addBadgesTab();
  addPrimaryBadgeToPost();
  addBadgesToUserCard();
  addBadgesToPostFooter();
});
