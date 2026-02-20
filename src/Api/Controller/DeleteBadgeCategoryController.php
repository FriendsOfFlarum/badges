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
use FoF\Badges\BadgeCategory;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class DeleteBadgeCategoryController extends AbstractDeleteController
{
    protected function delete(ServerRequestInterface $request): void
    {
        $actor = RequestUtil::getActor($request);

        $id = Arr::get($request->getQueryParams(), 'id');
        $category = BadgeCategory::findOrFail($id);

        $actor->assertCan('delete', $category);

        // Badges in this category will have category_id set to null
        // due to the foreign key constraint with ON DELETE SET NULL
        $category->delete();
    }
}
