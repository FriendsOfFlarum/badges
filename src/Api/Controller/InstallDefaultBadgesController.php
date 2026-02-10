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

use Carbon\Carbon;
use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use FoF\Badges\Api\Serializer\BadgeSerializer;
use FoF\Badges\Badge;
use FoF\Badges\BadgeCategory;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class InstallDefaultBadgesController extends AbstractListController
{
    public $serializer = BadgeSerializer::class;

    public $include = ['category'];

    protected function data(ServerRequestInterface $request, Document $document): iterable
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        // Only allow if no badges exist yet
        if (Badge::count() > 0) {
            return [];
        }

        $now = Carbon::now();

        $categories = $this->createCategories($now);
        $this->createBadges($categories, $now);

        return Badge::query()
            ->with('category')
            ->orderBy('order')
            ->get();
    }

    protected function createCategories(Carbon $now): array
    {
        $definitions = [
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Badges for new members taking their first steps',
                'order' => 1,
            ],
            [
                'name' => 'Milestones',
                'slug' => 'milestones',
                'description' => 'Badges for reaching important milestones',
                'order' => 2,
            ],
            [
                'name' => 'Posting',
                'slug' => 'posting',
                'description' => 'Badges for posting milestones and contributions',
                'order' => 3,
            ],
        ];

        $ids = [];

        foreach ($definitions as $def) {
            $category = BadgeCategory::build($def['name'], $def['slug'], $def['description']);
            $category->order = $def['order'];
            $category->save();
            $ids[$def['slug']] = $category->id;
        }

        return $ids;
    }

    protected function createBadges(array $categoryIds, Carbon $now): void
    {
        $trigger = fn (string $metric, string $operator, int $value) => [
            'logic' => 'AND',
            'conditions' => [
                ['metric' => $metric, 'operator' => $operator, 'value' => $value],
            ],
        ];

        $actions = ['send_notification' => true];

        $badges = [
            // Getting Started
            [
                'name' => 'First Post',
                'slug' => 'first-post',
                'description' => 'Write your first reply',
                'icon' => 'fas fa-pencil-alt',
                'icon_color' => '#ffffff',
                'background_color' => '#3498db',
                'category_id' => $categoryIds['getting-started'],
                'order' => 1,
                'trigger_config' => $trigger('post_count', '>=', 1),
            ],
            [
                'name' => 'Conversation Starter',
                'slug' => 'conversation-starter',
                'description' => 'Start your first discussion',
                'icon' => 'fas fa-comments',
                'icon_color' => '#ffffff',
                'background_color' => '#9b59b6',
                'category_id' => $categoryIds['getting-started'],
                'order' => 2,
                'trigger_config' => $trigger('discussion_count', '>=', 1),
            ],
            [
                'name' => 'Profile Picture',
                'slug' => 'profile-picture',
                'description' => 'Upload a profile picture',
                'icon' => 'fas fa-camera',
                'icon_color' => '#ffffff',
                'background_color' => '#f39c12',
                'category_id' => $categoryIds['getting-started'],
                'order' => 3,
                'trigger_config' => $trigger('has_avatar', '==', 1),
            ],

            // Milestones
            [
                'name' => 'First Week',
                'slug' => 'first-week',
                'description' => 'Be a member for 7 days',
                'icon' => 'fas fa-calendar-check',
                'icon_color' => '#ffffff',
                'background_color' => '#4caf50',
                'category_id' => $categoryIds['milestones'],
                'order' => 1,
                'trigger_config' => $trigger('member_days', '>=', 7),
            ],
            [
                'name' => 'Regular',
                'slug' => 'regular',
                'description' => 'Be a member for 30 days',
                'icon' => 'fas fa-calendar-alt',
                'icon_color' => '#ffffff',
                'background_color' => '#2196f3',
                'category_id' => $categoryIds['milestones'],
                'order' => 2,
                'trigger_config' => $trigger('member_days', '>=', 30),
            ],
            [
                'name' => 'Anniversary',
                'slug' => 'anniversary',
                'description' => 'Be a member for one year',
                'icon' => 'fas fa-birthday-cake',
                'icon_color' => '#ffffff',
                'background_color' => '#00bcd4',
                'category_id' => $categoryIds['milestones'],
                'order' => 3,
                'trigger_config' => $trigger('member_days', '>=', 365),
            ],

            // Posting
            [
                'name' => 'Active Member',
                'slug' => 'active-member',
                'description' => 'Post 50 replies',
                'icon' => 'fas fa-fire',
                'icon_color' => '#ffffff',
                'background_color' => '#ff5722',
                'category_id' => $categoryIds['posting'],
                'order' => 1,
                'trigger_config' => $trigger('post_count', '>=', 50),
            ],
            [
                'name' => 'Prolific',
                'slug' => 'prolific',
                'description' => 'Post 200 replies',
                'icon' => 'fas fa-medal',
                'icon_color' => '#ffffff',
                'background_color' => '#795548',
                'category_id' => $categoryIds['posting'],
                'order' => 2,
                'trigger_config' => $trigger('post_count', '>=', 200),
            ],
            [
                'name' => 'Topic Starter',
                'slug' => 'topic-starter',
                'description' => 'Start 10 discussions',
                'icon' => 'fas fa-lightbulb',
                'icon_color' => '#ffffff',
                'background_color' => '#8bc34a',
                'category_id' => $categoryIds['posting'],
                'order' => 3,
                'trigger_config' => $trigger('discussion_count', '>=', 10),
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Edit 10 posts to improve them',
                'icon' => 'fas fa-edit',
                'icon_color' => '#ffffff',
                'background_color' => '#607d8b',
                'category_id' => $categoryIds['posting'],
                'order' => 4,
                'trigger_config' => $trigger('edit_count', '>=', 10),
            ],
        ];

        foreach ($badges as $def) {
            $badge = Badge::build(
                $def['name'],
                $def['slug'],
                $def['description'],
                $def['category_id']
            );
            $badge->icon = $def['icon'];
            $badge->icon_color = $def['icon_color'];
            $badge->background_color = $def['background_color'];
            $badge->order = $def['order'];
            $badge->trigger_config = $def['trigger_config'];
            $badge->actions = $actions;
            $badge->save();
        }
    }
}
