<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\User\Exception\PermissionDeniedException;
use FoF\Badges\Api\Serializer\UserBadgeSerializer;
use FoF\Badges\Badge;
use FoF\Badges\UserBadge;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListBadgeHoldersController extends AbstractListController
{
    public $serializer = UserBadgeSerializer::class;

    public $include = ['user', 'badge'];

    public $optionalInclude = ['grantedByUser'];

    public $sort = ['earnedAt' => 'desc'];

    public $sortFields = ['earnedAt', 'userId'];

    public $limit = 20;

    public $maxLimit = 50;

    protected UrlGenerator $url;

    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }

    protected function data(ServerRequestInterface $request, Document $document): iterable
    {
        $actor = RequestUtil::getActor($request);

        // Allow access for users with viewList or moderate permission
        // This is consistent with ListUserBadgesController which also allows viewList
        if (! $actor->can('badges.viewList') && ! $actor->can('badges.moderate')) {
            throw new PermissionDeniedException();
        }

        $badgeId = Arr::get($request->getQueryParams(), 'id');

        if (! $badgeId) {
            return [];
        }

        // Verify badge exists
        $badge = Badge::findOrFail($badgeId);

        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);
        $filter = Arr::get($request->getQueryParams(), 'filter', []);
        $search = Arr::get($filter, 'q', '');

        $query = UserBadge::query()
            ->where('badge_id', $badgeId)
            ->with(['user', 'badge', 'grantedByUser']);

        // Search by username or display name
        if (! empty($search)) {
            // Escape special LIKE characters and trim
            $search = trim($search);
            $escapedSearch = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search);
            $query->whereHas('user', function ($q) use ($escapedSearch) {
                $q->where('username', 'LIKE', "%{$escapedSearch}%")
                    ->orWhere('display_name', 'LIKE', "%{$escapedSearch}%");
            });
        }

        $totalCount = $query->count();

        $results = $query
            ->orderBy('earned_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        // Add pagination links
        $document->addPaginationLinks(
            $this->url->to('api')->route('badges.holders', ['id' => $badgeId]),
            $request->getQueryParams(),
            $offset,
            $limit,
            $totalCount
        );

        // Add meta with total count
        $document->setMeta(['total' => $totalCount]);

        return $results;
    }
}
