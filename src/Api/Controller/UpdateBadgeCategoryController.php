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
use FoF\Badges\Api\Serializer\BadgeCategorySerializer;
use FoF\Badges\BadgeCategory;
use FoF\Badges\BadgeCategoryValidator;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class UpdateBadgeCategoryController extends AbstractShowController
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

        $id = Arr::get($request->getQueryParams(), 'id');
        $category = BadgeCategory::findOrFail($id);

        $actor->assertCan('edit', $category);

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        // Build validation data from what's being updated
        $validationData = [];
        if (Arr::has($data, 'name')) {
            $validationData['name'] = Arr::get($data, 'name');
        }
        if (Arr::has($data, 'slug')) {
            $validationData['slug'] = Arr::get($data, 'slug');
        }
        if (Arr::has($data, 'description')) {
            $validationData['description'] = Arr::get($data, 'description');
        }

        // Only validate fields that are being updated
        if (! empty($validationData)) {
            $this->validator->setCategory($category);
            $this->validator->assertValid($validationData);
        }

        if (Arr::has($data, 'name')) {
            $category->name = Arr::get($data, 'name');
        }

        if (Arr::has($data, 'slug')) {
            $category->slug = Arr::get($data, 'slug');
        }

        if (Arr::has($data, 'description')) {
            $category->description = Arr::get($data, 'description');
        }

        if (Arr::has($data, 'isEnabled')) {
            $category->is_enabled = (bool) Arr::get($data, 'isEnabled');
        }

        if (Arr::has($data, 'order')) {
            $category->order = (int) Arr::get($data, 'order');
        }

        $category->save();

        return $category;
    }
}
