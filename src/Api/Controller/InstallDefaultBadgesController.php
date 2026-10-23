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

use Carbon\Carbon;
use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use FoF\Badges\Api\Serializer\BadgeSerializer;
use FoF\Badges\Badge;
use FoF\Badges\BadgeCategory;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tobscure\JsonApi\Document;

class InstallDefaultBadgesController extends AbstractListController
{
    public $serializer = BadgeSerializer::class;

    public $include = ['category'];

    public function __construct(protected TranslatorInterface $translator)
    {
    }

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
                'slug' => 'getting-started',
                'order' => 1,
            ],
            [
                'slug' => 'milestones',
                'order' => 2,
            ],
            [
                'slug' => 'posting',
                'order' => 3,
            ],
        ];

        $ids = [];

        foreach ($definitions as $def) {
            $category = BadgeCategory::build(
                $this->translator->trans('fof-badges.defaults.categories.'.$def['slug'].'.name'),
                $def['slug'],
                $this->translator->trans('fof-badges.defaults.categories.'.$def['slug'].'.description')
            );
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
                'slug' => 'first-post',
                'icon' => 'fas fa-pencil-alt',
                'icon_color' => '#ffffff',
                'background_color' => '#3498db',
                'category_id' => $categoryIds['getting-started'],
                'order' => 1,
                'trigger_config' => $trigger('post_count', '>=', 1),
            ],
            [
                'slug' => 'conversation-starter',
                'icon' => 'fas fa-comments',
                'icon_color' => '#ffffff',
                'background_color' => '#9b59b6',
                'category_id' => $categoryIds['getting-started'],
                'order' => 2,
                'trigger_config' => $trigger('discussion_count', '>=', 1),
            ],
            [
                'slug' => 'profile-picture',
                'icon' => 'fas fa-camera',
                'icon_color' => '#ffffff',
                'background_color' => '#f39c12',
                'category_id' => $categoryIds['getting-started'],
                'order' => 3,
                'trigger_config' => $trigger('has_avatar', '==', 1),
            ],

            // Milestones
            [
                'slug' => 'first-week',
                'icon' => 'fas fa-calendar-check',
                'icon_color' => '#ffffff',
                'background_color' => '#4caf50',
                'category_id' => $categoryIds['milestones'],
                'order' => 1,
                'trigger_config' => $trigger('member_days', '>=', 7),
            ],
            [
                'slug' => 'regular',
                'icon' => 'fas fa-calendar-alt',
                'icon_color' => '#ffffff',
                'background_color' => '#2196f3',
                'category_id' => $categoryIds['milestones'],
                'order' => 2,
                'trigger_config' => $trigger('member_days', '>=', 30),
            ],
            [
                'slug' => 'anniversary',
                'icon' => 'fas fa-birthday-cake',
                'icon_color' => '#ffffff',
                'background_color' => '#00bcd4',
                'category_id' => $categoryIds['milestones'],
                'order' => 3,
                'trigger_config' => $trigger('member_days', '>=', 365),
            ],

            // Posting
            [
                'slug' => 'active-member',
                'icon' => 'fas fa-fire',
                'icon_color' => '#ffffff',
                'background_color' => '#ff5722',
                'category_id' => $categoryIds['posting'],
                'order' => 1,
                'trigger_config' => $trigger('post_count', '>=', 50),
            ],
            [
                'slug' => 'prolific',
                'icon' => 'fas fa-medal',
                'icon_color' => '#ffffff',
                'background_color' => '#795548',
                'category_id' => $categoryIds['posting'],
                'order' => 2,
                'trigger_config' => $trigger('post_count', '>=', 200),
            ],
            [
                'slug' => 'topic-starter',
                'icon' => 'fas fa-lightbulb',
                'icon_color' => '#ffffff',
                'background_color' => '#8bc34a',
                'category_id' => $categoryIds['posting'],
                'order' => 3,
                'trigger_config' => $trigger('discussion_count', '>=', 10),
            ],
            [
                'slug' => 'editor',
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
                $this->translator->trans('fof-badges.defaults.badges.'.$def['slug'].'.name'),
                $def['slug'],
                $this->translator->trans('fof-badges.defaults.badges.'.$def['slug'].'.description'),
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
