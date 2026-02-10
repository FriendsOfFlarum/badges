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

use Flarum\Http\RequestUtil;
use FoF\Badges\UserBadge;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ToggleUserBadgeController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');
        $body = $request->getParsedBody();
        $action = $body['action'] ?? null;

        $userBadge = UserBadge::findOrFail($id);

        // Only the badge owner can toggle their badge settings
        if ($userBadge->user_id !== $actor->id) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        if ($action === 'toggleFavorite') {
            // If setting as favorite, unset any existing favorite first
            if (!$userBadge->is_primary) {
                UserBadge::where('user_id', $actor->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
                $userBadge->is_primary = true;
                // Favorite badge must always be visible
                $userBadge->show_on_card = true;
            } else {
                $userBadge->is_primary = false;
            }
            $userBadge->save();

            return new JsonResponse([
                'success' => true,
                'is_primary' => $userBadge->is_primary,
                'show_on_card' => $userBadge->show_on_card,
            ]);
        }

        if ($action === 'toggleVisibility') {
            // Cannot hide a favorite badge
            if ($userBadge->is_primary && $userBadge->show_on_card) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Cannot hide favorite badge',
                ], 400);
            }
            $userBadge->show_on_card = !$userBadge->show_on_card;
            $userBadge->save();

            return new JsonResponse([
                'success' => true,
                'show_on_card' => $userBadge->show_on_card,
            ]);
        }

        return new JsonResponse(['error' => 'Invalid action'], 400);
    }
}
