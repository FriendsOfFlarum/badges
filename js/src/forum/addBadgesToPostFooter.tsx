import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import Link from 'flarum/common/components/Link';

export default function addBadgesToPostFooter() {
  // Helper to get visible badges array from user attributes
  const getVisibleBadges = (user: any): Array<{ name: string; icon: string }> => {
    return (user.attribute('visibleBadges') as Array<{ name: string; icon: string }>) || [];
  };

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
}
