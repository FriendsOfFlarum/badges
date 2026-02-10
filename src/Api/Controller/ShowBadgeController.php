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

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use FoF\Badges\Api\Serializer\BadgeSerializer;
use FoF\Badges\Badge;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowBadgeController extends AbstractShowController
{
    public $serializer = BadgeSerializer::class;

    public $include = ['category'];

    public $optionalInclude = ['userBadges', 'userBadges.user'];

    protected function data(ServerRequestInterface $request, Document $document): Badge
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');

        $badge = Badge::findOrFail($id);

        $actor->assertCan('view', $badge);

        return $badge;
    }
}
