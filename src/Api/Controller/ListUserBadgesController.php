<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\User\Exception\PermissionDeniedException;
use FoF\Badges\Api\Serializer\UserBadgeSerializer;
use FoF\Badges\UserBadge;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListUserBadgesController extends AbstractListController
{
    public $serializer = UserBadgeSerializer::class;

    public $include = ['badge', 'user'];

    public $optionalInclude = ['grantedByUser', 'badge.category'];

    public $sort = ['earnedAt' => 'desc'];

    public $sortFields = ['earnedAt'];

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

        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);
        $filter = Arr::get($request->getQueryParams(), 'filter', []);

        $userId = Arr::get($filter, 'user');
        $badgeId = Arr::get($filter, 'badge');

        $query = UserBadge::query();

        // Determine if actor can see hidden badges
        $canSeeHidden = $actor->can('badges.giveManually') || $actor->can('badges.moderate');

        // Filter by user
        if ($userId) {
            $query->where('user_id', $userId);

            // Check permission if viewing someone else's badges
            if ((int) $userId !== $actor->id) {
                $actor->assertCan('viewAny', UserBadge::class);

                // If not owner and not moderator, hide badges with show_on_card = false
                if (! $canSeeHidden) {
                    $query->where('show_on_card', true);
                }
            }
            // If viewing own badges, show all (including hidden ones)
        } else {
            // Without user filter, require viewList or moderate permission
            if (! $actor->can('badges.viewList') && ! $actor->can('badges.moderate')) {
                throw new PermissionDeniedException();
            }

            // If not moderator, only show visible badges
            if (! $canSeeHidden) {
                $query->where('show_on_card', true);
            }
        }

        // Filter by badge
        if ($badgeId) {
            $query->where('badge_id', $badgeId);
        }

        $totalCount = $query->count();

        $results = $query
            ->with(['badge', 'user'])
            ->orderBy('earned_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        // Add pagination links
        $document->addPaginationLinks(
            $this->url->to('api')->route('user-badges.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $totalCount
        );

        // Add total count to meta
        $document->setMeta([
            'total' => $totalCount,
        ]);

        return $results;
    }
}
