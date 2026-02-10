import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type Mithril from 'mithril';

export default class BadgeNotification extends Notification {
  icon(): string {
    return 'fas fa-award';
  }

  href(): string {
    const notification = this.attrs.notification;
    const content = (notification.content() || {}) as Record<string, any>;

    // Get badge ID from notification content
    if (content.badgeId) {
      return `${app.route('badges')}?badge=${content.badgeId}`;
    }

    // Default to badges page
    return app.route('badges');
  }

  content(): Mithril.Children {
    const notification = this.attrs.notification;
    const content = (notification.content() || {}) as Record<string, any>;
    const badgeName = content.badgeName || app.translator.trans('fof-badges.forum.notification.a_badge');

    // Get badge colors for styling if available
    const badgeIcon = content.badgeIcon || 'fas fa-award';
    const badgeIconColor = content.badgeIconColor || '#ffffff';
    const badgeBackgroundColor = content.badgeBackgroundColor || '#667eea';

    return (
      <span className="BadgeNotification-content">
        {app.translator.trans('fof-badges.forum.notification.badge_earned', {
          badge: (
            <span
              className="BadgeNotification-badge"
              style={{
                backgroundColor: badgeBackgroundColor,
                color: badgeIconColor,
              }}
            >
              <i className={badgeIcon}></i>
              <span>{badgeName}</span>
            </span>
          ),
        })}
      </span>
    );
  }

  excerpt(): Mithril.Children {
    return null;
  }
}
