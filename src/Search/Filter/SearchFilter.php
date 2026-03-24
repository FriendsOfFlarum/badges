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
 * Filters user badges by searching user's username and display_name.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class SearchFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'q';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $search = trim(is_array($value) ? $value[0] : $value);

        if (empty($search)) {
            return;
        }

        $escapedSearch = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search);

        /** @var DatabaseSearchState $state */
        $state->getQuery()->whereHas('user', function ($q) use ($escapedSearch, $negate) {
            $method = $negate ? 'whereNot' : 'where';
            $q->$method(function ($inner) use ($escapedSearch) {
                $inner->where('username', 'LIKE', "%{$escapedSearch}%")
                    ->orWhere('display_name', 'LIKE', "%{$escapedSearch}%");
            });
        });
    }
}
