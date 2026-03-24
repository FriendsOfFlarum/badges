<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * @implements FilterInterface<DatabaseSearchState>
 */
class BadgeFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'badge';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $badgeId = $this->asInt($value);

        /** @var DatabaseSearchState $state */
        $state->getQuery()->where('fof_badge_user.badge_id', $negate ? '!=' : '=', $badgeId);
    }
}
