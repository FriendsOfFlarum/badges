import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserCard from 'flarum/forum/components/UserCard';
import Link from 'flarum/common/components/Link';

export default function addBadgesToUserCard() {
  extend(UserCard.prototype, 'infoItems', function (items) {
    const user = (this as any).attrs.user;
    if (!user) return;

    if (app.forum.attribute('showBadgesOnUserCard') === false) return;

    const badgeCount = (user.attribute('badgeCount') as number) || 0;
    if (badgeCount === 0) return;

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
}
