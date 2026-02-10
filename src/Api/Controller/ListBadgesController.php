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
use FoF\Badges\Api\Serializer\BadgeSerializer;
use FoF\Badges\Badge;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListBadgesController extends AbstractListController
{
    public $serializer = BadgeSerializer::class;

    public $include = ['category'];

    public $optionalInclude = [];

    public $sort = ['order' => 'asc'];

    public $sortFields = ['order', 'name', 'earnedCount', 'createdAt'];

    public $limit = 50;

    public $maxLimit = 100;

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

        $query = Badge::query();

        // Filter by category
        if ($categoryId = Arr::get($filter, 'category')) {
            $query->where('category_id', $categoryId);
        }

        // Filter by active status
        if (Arr::has($filter, 'active')) {
            $query->where('is_active', (bool) Arr::get($filter, 'active'));
        } elseif (! $actor->hasPermission('badges.moderate')) {
            // Non-admins only see active badges by default
            $query->where('is_active', true);
        }

        // Guests only see visible badges
        if ($actor->isGuest()) {
            $query->where('is_visible', true);
        }

        $totalCount = $query->count();

        $results = $query
            ->orderBy('order', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();

        // Add pagination links
        $document->addPaginationLinks(
            $this->url->to('api')->route('badges.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $totalCount
        );

        return $results;
    }
}
