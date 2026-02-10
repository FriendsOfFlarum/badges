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
        $filter = Arr::get($request->getQueryParams(), 'filter', []);

        $query = BadgeCategory::query()
            ->withCount('badges')
            ->orderBy('order', 'asc');

        // Non-moderators only see enabled categories by default
        if (! $actor->hasPermission('badges.moderate') || ! Arr::get($filter, 'includeDisabled')) {
            $query->where('is_enabled', true);
        }

        return $query->get();
    }
}
