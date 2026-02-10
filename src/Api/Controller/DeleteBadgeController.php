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

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use FoF\Badges\Badge;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class DeleteBadgeController extends AbstractDeleteController
{
    protected function delete(ServerRequestInterface $request): void
    {
        $actor = RequestUtil::getActor($request);

        $id = Arr::get($request->getQueryParams(), 'id');
        $badge = Badge::findOrFail($id);

        $actor->assertCan('delete', $badge);

        // user_badges will be deleted automatically due to
        // the foreign key constraint with ON DELETE CASCADE
        $badge->delete();
    }
}
