<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges;

use Flarum\Foundation\AbstractValidator;

class BadgeCategoryValidator extends AbstractValidator
{
    protected ?BadgeCategory $category = null;

    public function setCategory(?BadgeCategory $category): void
    {
        $this->category = $category;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function getRules(): array
    {
        $idExcept = $this->category ? $this->category->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $idExcept ? "unique:fof_badge_cat,slug,{$idExcept}" : 'unique:fof_badge_cat,slug',
            ],
            'description' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
