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
use FoF\Badges\BadgeValidator;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class UpdateBadgeController extends AbstractShowController
{
    public $serializer = BadgeSerializer::class;

    public $include = ['category'];

    protected BadgeValidator $validator;

    public function __construct(BadgeValidator $validator)
    {
        $this->validator = $validator;
    }

    protected function data(ServerRequestInterface $request, Document $document): Badge
    {
        $actor = RequestUtil::getActor($request);

        $id = Arr::get($request->getQueryParams(), 'id');
        $badge = Badge::findOrFail($id);

        $actor->assertCan('edit', $badge);

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
        if (Arr::has($data, 'icon')) {
            $validationData['icon'] = Arr::get($data, 'icon');
        }
        if (Arr::has($data, 'iconColor')) {
            $validationData['icon_color'] = Arr::get($data, 'iconColor');
        }
        if (Arr::has($data, 'backgroundColor')) {
            $validationData['background_color'] = Arr::get($data, 'backgroundColor');
        }
        // Validate trigger_config if provided and not null
        if (Arr::has($data, 'triggerConfig') && Arr::get($data, 'triggerConfig') !== null) {
            $validationData['trigger_config'] = Arr::get($data, 'triggerConfig');
        }

        // Only validate fields that are being updated
        if (! empty($validationData)) {
            $this->validator->setBadge($badge);
            $this->validator->assertValid($validationData);
        }

        if (Arr::has($data, 'name')) {
            $badge->name = Arr::get($data, 'name');
        }

        if (Arr::has($data, 'slug')) {
            $badge->slug = Arr::get($data, 'slug');
        }

        if (Arr::has($data, 'description')) {
            $badge->description = Arr::get($data, 'description');
        }

        if (array_key_exists('categoryId', $data)) {
            $badge->category_id = Arr::get($data, 'categoryId');
        }

        if (Arr::has($data, 'icon')) {
            $badge->icon = Arr::get($data, 'icon');
        }

        if (Arr::has($data, 'iconColor')) {
            $badge->icon_color = Arr::get($data, 'iconColor');
        }

        if (Arr::has($data, 'backgroundColor')) {
            $badge->background_color = Arr::get($data, 'backgroundColor');
        }

        if (Arr::has($data, 'triggerConfig')) {
            $badge->trigger_config = Arr::get($data, 'triggerConfig');
        }

        if (Arr::has($data, 'actions')) {
            $badge->actions = Arr::get($data, 'actions');
        }

        if (Arr::has($data, 'isActive')) {
            $badge->is_active = (bool) Arr::get($data, 'isActive');
        }

        if (Arr::has($data, 'isVisible')) {
            $badge->is_visible = (bool) Arr::get($data, 'isVisible');
        }

        if (Arr::has($data, 'order')) {
            $badge->order = (int) Arr::get($data, 'order');
        }

        $badge->save();

        return $badge;
    }
}
