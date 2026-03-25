import Form from 'flarum/common/components/Form';
import app from 'flarum/admin/app';
import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Avatar from 'flarum/common/components/Avatar';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { Badge, UserBadge } from '../../common';

interface ManualAssignModalAttrs extends IFormModalAttrs {
  badge: Badge;
  onAssign?: () => void;
}

export default class ManualAssignModal extends FormModal<ManualAssignModalAttrs> {
  badge!: Badge;

  // Current tab: 'assign' or 'holders'
  activeTab: 'assign' | 'holders' = 'assign';

  // Assign tab state
  searchQuery: string = '';
  searchResults: User[] = [];
  selectedUser: User | null = null;
  reason: string = '';
  loading: boolean = false;
  searching: boolean = false;
  searchTimeout: ReturnType<typeof setTimeout> | null = null;

  // Holders tab state
  holders: UserBadge[] = [];
  holdersLoading: boolean = false;
  holdersSearch: string = '';
  holdersSearchTimeout: ReturnType<typeof setTimeout> | null = null;
  holdersOffset: number = 0;
  holdersLimit: number = 20;
  holdersTotal: number = 0;
  revokingId: string | null = null;

  oninit(vnode: Mithril.Vnode<ManualAssignModalAttrs>) {
    super.oninit(vnode);
    this.badge = this.attrs.badge;

    // Load holders count initially
    this.loadHolders();
  }

  className(): string {
    return 'ManualAssignModal ManualAssignModal--tabbed';
  }

  title(): Mithril.Children {
    return app.translator.trans('fof-badges.admin.assign_badge', {
      badge: this.badge.name(),
    });
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        {/* Tab Navigation */}
        <div className="ManualAssignModal-tabs">
          <button className={`ManualAssignModal-tab ${this.activeTab === 'assign' ? 'active' : ''}`} onclick={() => this.switchTab('assign')}>
            <i className="fas fa-user-plus"></i>
            {app.translator.trans('fof-badges.admin.tab_assign')}
          </button>
          <button className={`ManualAssignModal-tab ${this.activeTab === 'holders' ? 'active' : ''}`} onclick={() => this.switchTab('holders')}>
            <i className="fas fa-users"></i>
            {app.translator.trans('fof-badges.admin.tab_holders', {
              count: this.holdersTotal,
            })}
          </button>
        </div>

        {/* Tab Content */}
        <div className="ManualAssignModal-tabContent">{this.activeTab === 'assign' ? this.renderAssignTab() : this.renderHoldersTab()}</div>
      </div>
    );
  }

  renderAssignTab(): Mithril.Children {
    return (
      <Form>
        {}
        <div className="Form-group">
          <label>{app.translator.trans('fof-badges.admin.select_user')}</label>
          {this.selectedUser ? (
            <div className="ManualAssignModal-selectedUser">
              <Avatar user={this.selectedUser} />
              <div className="ManualAssignModal-selectedUser-info">
                <span className="displayName">{this.selectedUser.displayName()}</span>
                <span className="username">@{this.selectedUser.username()}</span>
              </div>
              <Button
                className="Button Button--icon"
                icon="fas fa-times"
                onclick={() => {
                  this.selectedUser = null;
                }}
                title={app.translator.trans('fof-badges.admin.clear_selection')}
              />
            </div>
          ) : (
            <div className="ManualAssignModal-search">
              <div className="ManualAssignModal-searchInput">
                <input
                  type="text"
                  className="FormControl"
                  placeholder={app.translator.trans('fof-badges.admin.search_user_placeholder')}
                  value={this.searchQuery}
                  oninput={(e: InputEvent) => {
                    this.onSearchInput((e.target as HTMLInputElement).value);
                  }}
                />
                {this.searching && (
                  <span className="ManualAssignModal-searchSpinner">
                    <LoadingIndicator size="small" display="inline" />
                  </span>
                )}
              </div>
              {this.searchResults.length > 0 && (
                <ul className="ManualAssignModal-results">
                  {this.searchResults.map((user) => (
                    <li key={user.id()} className="ManualAssignModal-resultItem" onclick={() => this.selectUser(user)}>
                      <Avatar user={user} />
                      <div className="ManualAssignModal-resultItem-info">
                        <span className="displayName">{user.displayName()}</span>
                        <span className="username">@{user.username()}</span>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
              {this.searchQuery.length >= 2 && !this.searching && this.searchResults.length === 0 && (
                <div className="ManualAssignModal-noResults">{app.translator.trans('fof-badges.admin.no_users_found')}</div>
              )}
            </div>
          )}
        </div>
        {}
        <div className="Form-group">
          <label>{app.translator.trans('fof-badges.admin.assign_reason')}</label>
          <textarea
            className="FormControl"
            value={this.reason}
            oninput={(e: InputEvent) => {
              this.reason = (e.target as HTMLTextAreaElement).value;
            }}
            rows={3}
            placeholder={app.translator.trans('fof-badges.admin.assign_reason_placeholder')}
          />
          <p className="helpText">{app.translator.trans('fof-badges.admin.assign_reason_help')}</p>
        </div>
        {}
        <div className="Form-group">
          <Button className="Button Button--primary" loading={this.loading} disabled={!this.selectedUser} onclick={() => this.assign()}>
            {app.translator.trans('fof-badges.admin.assign')}
          </Button>
        </div>
      </Form>
    );
  }

  renderHoldersTab(): Mithril.Children {
    return (
      <div className="ManualAssignModal-holders">
        {/* Search */}
        <div className="ManualAssignModal-holdersSearch">
          <div className="ManualAssignModal-searchInput">
            <i className="fas fa-search"></i>
            <input
              type="text"
              className="FormControl"
              placeholder={app.translator.trans('fof-badges.admin.search_holders_placeholder')}
              value={this.holdersSearch}
              oninput={(e: InputEvent) => {
                this.onHoldersSearchInput((e.target as HTMLInputElement).value);
              }}
            />
          </div>
        </div>
        {/* Holders List */}
        {this.holdersLoading ? (
          <div className="ManualAssignModal-holdersLoading">
            <LoadingIndicator />
          </div>
        ) : this.holders.length === 0 ? (
          <div className="ManualAssignModal-noHolders">
            {this.holdersSearch ? app.translator.trans('fof-badges.admin.no_holders_found') : app.translator.trans('fof-badges.admin.no_holders')}
          </div>
        ) : (
          <>
            <ul className="ManualAssignModal-holdersList">
              {this.holders
                .filter((userBadge) => userBadge.user())
                .map((userBadge) => {
                  const user = userBadge.user() as User;
                  const isRevoking = this.revokingId === userBadge.id();
                  const grantedBy = userBadge.grantedBy();
                  const grantedByUser = userBadge.grantedByUser();
                  const reason = userBadge.reason();

                  return (
                    <li key={userBadge.id()} className="ManualAssignModal-holderItem">
                      <div className="ManualAssignModal-holderItem-user">
                        <Avatar user={user} />
                        <div className="ManualAssignModal-holderItem-info">
                          <span className="displayName">{user.displayName()}</span>
                          <span className="username">@{user.username()}</span>
                          <div className="ManualAssignModal-holderItem-meta">
                            <span className="earnedAt">
                              <i className="fas fa-clock"></i>
                              {this.formatDate(userBadge.earnedAt())}
                            </span>
                            {grantedBy === 'manual' && grantedByUser && (
                              <span className="grantedBy">
                                <i className="fas fa-user-edit"></i>
                                {grantedByUser.displayName()}
                              </span>
                            )}
                            {grantedBy === 'system' && (
                              <span className="grantedBy">
                                <i className="fas fa-robot"></i>
                                {app.translator.trans('fof-badges.admin.granted_by_system')}
                              </span>
                            )}
                          </div>
                          {reason && <div className="ManualAssignModal-holderItem-reason">{reason}</div>}
                        </div>
                      </div>
                      <Button
                        className="Button Button--danger Button--icon"
                        icon="fas fa-trash"
                        loading={isRevoking}
                        onclick={() => this.revokeFromUser(userBadge, user.displayName())}
                        title={app.translator.trans('fof-badges.admin.revoke')}
                      />
                    </li>
                  );
                })}
            </ul>

            {/* Pagination */}
            {this.holdersTotal > this.holdersLimit && (
              <div className="ManualAssignModal-holdersPagination">
                <Button
                  className="Button"
                  disabled={this.holdersOffset === 0}
                  onclick={() => this.loadHoldersPage(this.holdersOffset - this.holdersLimit)}
                >
                  <i className="fas fa-chevron-left"></i>
                  {app.translator.trans('fof-badges.admin.previous')}
                </Button>
                <span className="ManualAssignModal-paginationInfo">
                  {app.translator.trans('fof-badges.admin.showing_holders', {
                    from: this.holdersOffset + 1,
                    to: Math.min(this.holdersOffset + this.holdersLimit, this.holdersTotal),
                    total: this.holdersTotal,
                  })}
                </span>
                <Button
                  className="Button"
                  disabled={this.holdersOffset + this.holdersLimit >= this.holdersTotal}
                  onclick={() => this.loadHoldersPage(this.holdersOffset + this.holdersLimit)}
                >
                  {app.translator.trans('fof-badges.admin.next')}
                  <i className="fas fa-chevron-right"></i>
                </Button>
              </div>
            )}
          </>
        )}
      </div>
    );
  }

  switchTab(tab: 'assign' | 'holders'): void {
    this.activeTab = tab;

    if (tab === 'holders' && this.holders.length === 0 && !this.holdersLoading) {
      this.loadHolders();
    }
  }

  formatDate(date: Date | null): string {
    if (!date) return '';
    return new Date(date).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();
    if (this.activeTab === 'assign') {
      this.assign();
    }
  }

  // Assign tab methods
  onSearchInput(query: string): void {
    this.searchQuery = query;

    if (this.searchTimeout) {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = null;
    }

    if (query.length < 2) {
      this.searchResults = [];
      this.searching = false;
      m.redraw();
      return;
    }

    this.searching = true;
    m.redraw();

    // Debounce search by 300ms
    this.searchTimeout = setTimeout(() => this.search(query), 300);
  }

  async search(query: string): Promise<void> {
    try {
      const users = await app.store.find<User[]>('users', {
        filter: { q: query },
        page: { limit: 10 },
      });
      this.searchResults = users as User[];
    } catch (error) {
      console.error('Failed to search users:', error);
      this.searchResults = [];
    } finally {
      this.searching = false;
      m.redraw();
    }
  }

  selectUser(user: User): void {
    this.selectedUser = user;
    this.searchQuery = '';
    this.searchResults = [];
    m.redraw();
  }

  async assign(): Promise<void> {
    if (!this.selectedUser) return;

    // Store values before async operation
    const userName = this.selectedUser.displayName();
    const badgeName = this.badge.name();
    const userId = this.selectedUser.id();
    const badgeId = this.badge.id();

    this.loading = true;
    m.redraw();

    try {
      await app.store.createRecord('user-badges').save({
        userId,
        badgeId,
        reason: this.reason || null,
      });

      app.alerts.show(
        { type: 'success' },
        app.translator.trans('fof-badges.admin.badge_assigned', {
          badge: badgeName,
          username: userName,
        })
      );

      // Reset form
      this.selectedUser = null;
      this.reason = '';

      // Refresh holders list
      this.loadHolders();

      this.attrs.onAssign?.();
    } catch (error) {
      // Error will be handled by Flarum's default error handler
      throw error;
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  // Holders tab methods
  onHoldersSearchInput(query: string): void {
    this.holdersSearch = query;

    if (this.holdersSearchTimeout) {
      clearTimeout(this.holdersSearchTimeout);
      this.holdersSearchTimeout = null;
    }

    // Debounce search by 300ms
    this.holdersSearchTimeout = setTimeout(() => {
      this.holdersOffset = 0;
      this.loadHolders();
    }, 300);
  }

  loadHoldersPage(offset: number): void {
    this.holdersOffset = Math.max(0, offset);
    this.loadHolders();
  }

  async loadHolders(): Promise<void> {
    this.holdersLoading = true;
    m.redraw();

    try {
      const params: Record<string, any> = {
        page: {
          offset: this.holdersOffset,
          limit: this.holdersLimit,
        },
        include: 'user,grantedByUser',
        filter: { badge: this.badge.id() },
      };

      if (this.holdersSearch) {
        params.filter.q = this.holdersSearch;
      }

      const response = await app.request<{
        data: any[];
        included?: any[];
        // Flarum 2.x wraps pagination info under meta.page
        meta?: {
          page?: { total?: number; offset?: number; limit?: number };
          total?: number;
        };
        links?: { next?: string };
      }>({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/user-badges',
        params,
      });

      // Parse the response using pushPayload to properly handle included relationships
      if (response.data) {
        // Use pushPayload to process the full JSON:API response including relationships
        const holders = app.store.pushPayload<UserBadge[]>(response as any);

        this.holders = Array.isArray(holders) ? holders : [holders];

        // Flarum 2.x returns meta.page.total; fallback to meta.total for compatibility
        this.holdersTotal = response.meta?.page?.total ?? response.meta?.total ?? this.holders.length;
      }
    } catch (error) {
      console.error('Failed to load badge holders:', error);
      this.holders = [];
      this.holdersTotal = 0;
    } finally {
      this.holdersLoading = false;
      m.redraw();
    }
  }

  async revokeFromUser(userBadge: UserBadge, userName: string): Promise<void> {
    const badgeName = this.badge.name();

    if (!confirm(extractText(app.translator.trans('fof-badges.admin.revoke_confirm', { badge: badgeName, username: userName })))) {
      return;
    }

    this.revokingId = userBadge.id() ?? null;
    m.redraw();

    try {
      await app.request({
        method: 'DELETE',
        url: `${app.forum.attribute('apiUrl')}/user-badges/${userBadge.id()}`,
      });

      app.alerts.show(
        { type: 'success' },
        app.translator.trans('fof-badges.admin.badge_revoked', {
          badge: badgeName,
          username: userName,
        })
      );

      // Remove from local list
      this.holders = this.holders.filter((h) => h.id() !== userBadge.id());
      this.holdersTotal = Math.max(0, this.holdersTotal - 1);

      this.attrs.onAssign?.();
    } catch (error) {
      console.error('Failed to revoke badge:', error);
      app.alerts.show({ type: 'error' }, app.translator.trans('fof-badges.admin.revoke_error'));
    } finally {
      this.revokingId = null;
      m.redraw();
    }
  }
}
