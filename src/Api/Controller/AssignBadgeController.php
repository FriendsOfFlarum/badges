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
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use FoF\Badges\Api\Serializer\UserBadgeSerializer;
use FoF\Badges\Badge;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\UserBadge;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class AssignBadgeController extends AbstractCreateController
{
    public $serializer = UserBadgeSerializer::class;

    public $include = ['badge', 'user'];

    protected BadgeAwarder $awarder;

    public function __construct(BadgeAwarder $awarder)
    {
        $this->awarder = $awarder;
    }

    protected function data(ServerRequestInterface $request, Document $document): UserBadge
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('create', UserBadge::class);

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        $userId = (int) Arr::get($data, 'userId');
        $badgeId = (int) Arr::get($data, 'badgeId');
        $reason = Arr::get($data, 'reason');

        // Validate user and badge exist
        $user = User::findOrFail($userId);
        $badge = Badge::findOrFail($badgeId);

        // Use BadgeAwarder to award the badge (handles actions, notifications, etc.)
        [$userBadge, $wasCreated] = $this->awarder->award(
            $user,
            $badge,
            UserBadge::GRANTED_BY_MANUAL,
            $actor
        );

        if (!$wasCreated) {
            throw new ValidationException([
                'badge' => 'User already has this badge.'
            ]);
        }

        // Update reason if provided
        if ($reason) {
            $userBadge->reason = $reason;
            $userBadge->save();
        }

        // Load relationships for response
        $userBadge->load(['badge', 'user']);

        return $userBadge;
    }
}
