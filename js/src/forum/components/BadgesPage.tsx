import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import type Mithril from 'mithril';
import type { Badge, BadgeCategory } from '../../common';
import BadgeCard from './BadgeCard';
import BadgeModal from './BadgeModal';

export default class BadgesPage extends Page {
  loading: boolean = true;
  badges: Badge[] = [];
  categories: BadgeCategory[] = [];

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.loadData();
  }

  oncreate(vnode: Mithril.VnodeDOM) {
    super.oncreate(vnode);
    app.setTitle(app.translator.trans('fof-badges.forum.badges_title') as string);
  }

  async loadData() {
    this.loading = true;
    m.redraw();

    try {
      const [badges, categories] = await Promise.all([app.store.find<Badge[]>('badges'), app.store.find<BadgeCategory[]>('badge-categories')]);

      // Filter to ensure only valid models are stored
      this.badges = (Array.isArray(badges) ? badges : []).filter((b) => b && typeof b.id === 'function' && b.isVisible()) as Badge[];
      this.categories = (Array.isArray(categories) ? categories : []).filter(
        (c) => c && typeof c.id === 'function' && c.isEnabled()
      ) as BadgeCategory[];

      // Check if specific badge is requested via URL query param
      const params = new URLSearchParams(window.location.search);
      const badgeId = params.get('badge');
      if (badgeId) {
        this.openBadgeById(badgeId);
      }
    } catch (error) {
      console.error('Failed to load badges:', error);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  openBadgeById(id: string): void {
    const badge = this.badges.find((b) => b.id() === id);
    if (badge) {
      setTimeout(() => this.showBadgeModal(badge), 100);
    }
  }

  view(): Mithril.Children {
    return (
      <PageStructure
        className="BadgesPage"
        hero={() => (
          <header className="Hero BadgesHero">
            <div className="container">
              <div className="containerNarrow">
                <h1 className="Hero-title">
                  <i aria-hidden="true" className="icon fas fa-award"></i> {app.translator.trans('fof-badges.forum.badges_title')}
                </h1>
              </div>
            </div>
          </header>
        )}
        sidebar={() => <IndexSidebar />}
      >
        {this.loading ? <LoadingIndicator /> : this.renderContent()}
      </PageStructure>
    );
  }

  renderContent(): Mithril.Children {
    if (this.badges.length === 0) {
      return (
        <div className="BadgesPage-empty">
          <i className="fas fa-award BadgesPage-empty-icon"></i>
          <p>{app.translator.trans('fof-badges.forum.no_badges')}</p>
        </div>
      );
    }

    return this.renderBadgesList();
  }

  renderBadgesList(): Mithril.Children {
    const uncategorized = this.badges.filter((b) => !b.category());
    const byCategory = this.categories
      .filter((cat) => cat && typeof cat.id === 'function') // Ensure valid model
      .map((cat) => ({
        category: cat,
        badges: this.badges.filter((b) => {
          const category = b.category();
          return category && typeof category.id === 'function' && category.id() === cat.id();
        }),
      }))
      .filter((g) => g.badges.length > 0);

    const canViewUserBadges = app.session.user && app.forum.attribute('canViewUserBadges');

    return (
      <div className="BadgesList">
        {canViewUserBadges && (
          <div className="BadgesList-header">
            <Link className="Button" href={app.route('user.badges', { username: app.session.user!.slug() })}>
              <i className="fas fa-user icon"></i>
              {app.translator.trans('fof-badges.forum.my_badges')}
            </Link>
          </div>
        )}
        {byCategory.map((group) => (
          <section className="BadgesList-category" key={group.category.id()}>
            <h2 className="BadgesList-categoryTitle">{group.category.name()}</h2>
            {group.category.description() && <p className="BadgesList-categoryDescription">{group.category.description()}</p>}
            <div className="BadgesList-grid">
              {group.badges.map((badge) => (
                <BadgeCard key={badge.id()} badge={badge} onclick={() => this.showBadgeModal(badge)} isOwned={badge.isEarned()} />
              ))}
            </div>
          </section>
        ))}

        {uncategorized.length > 0 && (
          <section className="BadgesList-category BadgesList-category--uncategorized">
            <h2 className="BadgesList-categoryTitle">{app.translator.trans('fof-badges.forum.other_badges')}</h2>
            <div className="BadgesList-grid">
              {uncategorized.map((badge) => (
                <BadgeCard key={badge.id()} badge={badge} onclick={() => this.showBadgeModal(badge)} isOwned={badge.isEarned()} />
              ))}
            </div>
          </section>
        )}
      </div>
    );
  }

  showBadgeModal(badge: Badge): void {
    const baseUrl = app.route('badges');
    const newUrl = `${baseUrl}?badge=${badge.id()}`;
    window.history.pushState(null, '', newUrl);

    app.modal.show(BadgeModal, {
      badge,
      onhide: () => {
        window.history.pushState(null, '', baseUrl);
      },
    });
  }
}
