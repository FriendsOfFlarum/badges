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

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\UserBadge;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class RevokeBadgeController extends AbstractDeleteController
{
    protected BadgeAwarder $awarder;

    public function __construct(BadgeAwarder $awarder)
    {
        $this->awarder = $awarder;
    }

    protected function delete(ServerRequestInterface $request): void
    {
        $actor = RequestUtil::getActor($request);

        $id = Arr::get($request->getQueryParams(), 'id');
        $userBadge = UserBadge::findOrFail($id);

        $actor->assertCan('delete', $userBadge);

        // Use BadgeAwarder::revoke() which properly dispatches BadgeRevoked event
        $this->awarder->revoke($userBadge);
    }
}
