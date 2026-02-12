import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import PostUser from 'flarum/forum/components/PostUser';
import Link from 'flarum/common/components/Link';

export default function addPrimaryBadgeToPost() {
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
}
