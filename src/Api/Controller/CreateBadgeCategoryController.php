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

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use FoF\Badges\Api\Serializer\BadgeCategorySerializer;
use FoF\Badges\BadgeCategory;
use FoF\Badges\BadgeCategoryValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class CreateBadgeCategoryController extends AbstractCreateController
{
    public $serializer = BadgeCategorySerializer::class;

    protected BadgeCategoryValidator $validator;

    public function __construct(BadgeCategoryValidator $validator)
    {
        $this->validator = $validator;
    }

    protected function data(ServerRequestInterface $request, Document $document): BadgeCategory
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('create', BadgeCategory::class);

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        $name = Arr::get($data, 'name');
        $slug = Arr::get($data, 'slug') ?: Str::slug($name);
        $description = Arr::get($data, 'description');

        // Validate input
        $this->validator->assertValid([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
        ]);

        // Get max order and add 1
        $maxOrder = BadgeCategory::query()->max('order') ?? 0;

        $category = BadgeCategory::build($name, $slug, $description);
        $category->order = $maxOrder + 1;
        $category->is_enabled = Arr::get($data, 'isEnabled', true);
        $category->save();

        return $category;
    }
}
