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
use FoF\Badges\Api\Serializer\BadgeSerializer;
use FoF\Badges\Badge;
use FoF\Badges\BadgeValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class CreateBadgeController extends AbstractCreateController
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
        $actor->assertCan('create', Badge::class);

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        $name = Arr::get($data, 'name');
        $slug = Arr::get($data, 'slug') ?: Str::slug($name);

        // Validate input
        $validationData = [
            'name' => $name,
            'slug' => $slug,
            'description' => Arr::get($data, 'description'),
            'icon' => Arr::get($data, 'icon'),
            'icon_color' => Arr::get($data, 'iconColor'),
            'background_color' => Arr::get($data, 'backgroundColor'),
        ];

        // Add trigger_config validation if provided
        if (Arr::has($data, 'triggerConfig') && Arr::get($data, 'triggerConfig') !== null) {
            $validationData['trigger_config'] = Arr::get($data, 'triggerConfig');
        }

        $this->validator->assertValid($validationData);

        $categoryId = Arr::get($data, 'categoryId');

        // Get max order and add 1
        $maxOrder = Badge::query()->max('order') ?? 0;

        $badge = Badge::build($name, $slug, Arr::get($data, 'description'), $categoryId);
        $badge->order = $maxOrder + 1;

        // Optional fields
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

        $badge->earned_count = 0;
        $badge->save();

        return $badge;
    }
}
