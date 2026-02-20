<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Controller;

use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;

class BadgeOverviewController
{
    public function __invoke(Document $document, ServerRequestInterface $request): Document
    {
        $actor = RequestUtil::getActor($request);

        // Check permission to view badge list
        $actor->assertPermission($actor->hasPermission('badges.viewList'));

        return $document;
    }
}
