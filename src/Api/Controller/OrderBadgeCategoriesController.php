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

use Flarum\Http\RequestUtil;
use FoF\Badges\BadgeCategory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OrderBadgeCategoriesController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
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

        return new JsonResponse(['success' => true]);
    }
}
