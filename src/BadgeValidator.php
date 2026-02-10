<?php

/*
 * This file is part of fof/badges.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Badges;

use Flarum\Foundation\AbstractValidator;

class BadgeValidator extends AbstractValidator
{
    protected ?Badge $badge = null;

    public function setBadge(?Badge $badge): void
    {
        $this->badge = $badge;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function getRules(): array
    {
        $idExcept = $this->badge ? $this->badge->id : null;

        return [
            'name' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:200',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $idExcept ? "unique:fof_badges,slug,{$idExcept}" : 'unique:fof_badges,slug',
            ],
            'description' => ['nullable', 'string', 'max:65535'],
            'icon' => ['nullable', 'string', 'max:100'],
            'icon_color' => ['nullable', 'string', 'max:50', 'regex:/^#[0-9a-fA-F]{6}$|^#[0-9a-fA-F]{3}$/'],
            'background_color' => ['nullable', 'string', 'max:50', 'regex:/^#[0-9a-fA-F]{6}$|^#[0-9a-fA-F]{3}$/'],
            // trigger_config validation
            'trigger_config' => ['nullable', 'array'],
            'trigger_config.logic' => ['required_with:trigger_config', 'in:AND,OR'],
            'trigger_config.conditions' => ['required_with:trigger_config', 'array', 'min:1'],
            'trigger_config.conditions.*.metric' => ['required', 'string'],
            'trigger_config.conditions.*.operator' => ['required', 'in:>=,<=,==,>,<,!='],
            'trigger_config.conditions.*.value' => ['required', 'integer', 'min:0'],
            'trigger_config.date_range.start' => ['nullable', 'date'],
            'trigger_config.date_range.end' => ['nullable', 'date', 'after_or_equal:trigger_config.date_range.start'],
        ];
    }
}
