import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';
import type { Badge } from '../../common';
import getRarityInfo from '../../common/helpers/getRarityInfo';

interface BadgeCardAttrs {
  badge: Badge;
  onclick?: () => void;
  earnedAt?: Date | string | null;
  isFavorite?: boolean;
  isHidden?: boolean;
  isNew?: boolean;
  isManual?: boolean;
  isOwner?: boolean;
  isOwned?: boolean;
  reason?: string | null;
}

export default class BadgeCard extends Component<BadgeCardAttrs> {
  view(): Mithril.Children {
    const badge = this.attrs.badge;
    const totalUsers = (app.forum.attribute('userCount') as number) || 1;
    const rarityInfo = getRarityInfo(badge.earnedCount(), totalUsers);
    const { earnedAt, isFavorite, isHidden, isNew, isManual, isOwner, isOwned, reason } = this.attrs;

    // Collect all indicators to show (order: owned, new, favorite, hidden, manual)
    const indicators: Mithril.Children[] = [];

    // Owned indicator (for global badges page)
    if (isOwned && !isOwner) {
      indicators.push(
        <span className="BadgeCard-indicator BadgeCard-indicator--owned" title={app.translator.trans('fof-badges.forum.owned_badge') as string}>
          <i className="fas fa-check"></i>
        </span>
      );
    }

    // New indicator
    if (isNew) {
      indicators.push(
        <span className="BadgeCard-indicator BadgeCard-indicator--new" title={app.translator.trans('fof-badges.forum.new_tag') as string}>
          <i className="fas fa-certificate"></i>
        </span>
      );
    }

    // Owner indicators (favorite, hidden)
    if (isOwner && isFavorite) {
      indicators.push(
        <span
          className="BadgeCard-indicator BadgeCard-indicator--favorite"
          title={app.translator.trans('fof-badges.forum.user.favorite_badge') as string}
        >
          <i className="fas fa-star"></i>
        </span>
      );
    }
    if (isOwner && isHidden) {
      indicators.push(
        <span
          className="BadgeCard-indicator BadgeCard-indicator--hidden"
          title={app.translator.trans('fof-badges.forum.user.hidden_badge') as string}
        >
          <i className="fas fa-eye-slash"></i>
        </span>
      );
    }

    // Manual indicator
    if (isManual) {
      indicators.push(
        <span
          className="BadgeCard-indicator BadgeCard-indicator--manual"
          title={app.translator.trans('fof-badges.forum.user.manually_awarded') as string}
        >
          <i className="fas fa-hand-holding"></i>
        </span>
      );
    }

    // Description: show reason if available, otherwise badge description
    const description = reason || badge.description();

    return (
      <div
        className="BadgeCard"
        onclick={(e: MouseEvent) => {
          e.preventDefault();
          this.attrs.onclick?.();
        }}
        role="button"
        tabindex={0}
        onkeydown={(e: KeyboardEvent) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.attrs.onclick?.();
          }
        }}
      >
        {indicators.length > 0 && <div className="BadgeCard-indicators">{indicators}</div>}
        <div
          className="BadgeCard-icon"
          style={{
            backgroundColor: badge.backgroundColor(),
            color: badge.iconColor(),
          }}
        >
          <i className={badge.icon()}></i>
        </div>
        <div className="BadgeCard-content">
          <div className="BadgeCard-name">{badge.name()}</div>
          {description && <div className="BadgeCard-description">{description}</div>}
          <div className="BadgeCard-stats">
            <span className={`BadgeCard-rarity BadgeCard-rarity--${rarityInfo.tier}`} style={{ color: rarityInfo.color }}>
              <i className="fas fa-gem"></i>
              {rarityInfo.label}
            </span>
            {earnedAt ? (
              <span className="BadgeCard-earnedAt">
                <i className="fas fa-calendar-check"></i>
                {humanTime(earnedAt instanceof Date ? earnedAt : new Date(earnedAt as string))}
              </span>
            ) : (
              <span className="BadgeCard-earned">
                <i className="fas fa-users"></i>
                {app.translator.trans('fof-badges.forum.earned_by', {
                  count: badge.earnedCount(),
                })}
              </span>
            )}
          </div>
        </div>
      </div>
    );
  }
}
