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

class OrderBadgeCategoriesController extends AbstractListController
{
    public $serializer = BadgeCategorySerializer::class;

    protected function data(ServerRequestInterface $request, Document $document): iterable
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('badges.moderate');

        $order = Arr::get($request->getParsedBody(), 'order', []);

        (new BadgeCategory())->getConnection()->transaction(function () use ($order) {
            foreach ($order as $item) {
                $id = Arr::get($item, 'id');
                $position = Arr::get($item, 'order');

                if ($id && $position !== null) {
                    BadgeCategory::where('id', $id)->update(['order' => (int) $position]);
                }
            }
        });

        return BadgeCategory::query()
            ->orderBy('order', 'asc')
            ->get();
    }
}
