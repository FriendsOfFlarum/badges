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
use FoF\Badges\Api\Serializer\BadgeCategorySerializer;
use FoF\Badges\BadgeCategory;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListBadgeCategoriesController extends AbstractListController
{
    public $serializer = BadgeCategorySerializer::class;

    public $include = [];

    public $optionalInclude = ['badges'];

    public $sort = ['order' => 'asc'];

    public $sortFields = ['order', 'name', 'createdAt'];

    protected function data(ServerRequestInterface $request, Document $document): iterable
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('viewAny', BadgeCategory::class);
        $filter = Arr::get($request->getQueryParams(), 'filter', []);

        $query = BadgeCategory::query()
            ->withCount(['badges' => fn ($query) => \FoF\Badges\BadgeVisibility::catalog($query, $actor)])
            ->orderBy('order', 'asc');

        if (! $actor->hasPermission('badges.moderate')) {
            $query->where('is_enabled', true);
        }

        // Explicit filters must not widen actor visibility.
        if (Arr::has($filter, 'enabled')) {
            $query->where('is_enabled', (bool) Arr::get($filter, 'enabled'));
        }

        return $query->get();
    }
}
