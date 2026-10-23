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
use Flarum\Foundation\ValidationException;
use Flarum\Group\Group;
use Flarum\User\User;

class BadgeValidator extends AbstractValidator
{
    protected ?Badge $badge = null;

    public function assertValid(array $attributes): void
    {
        if (array_key_exists('actions', $attributes)) {
            $id = self::actionGroupId($attributes['actions']);
            if ($id !== null && ! Group::whereKey($id)->exists()) {
                throw new ValidationException(['actions' => 'The selected action group does not exist.']);
            }
        }

        parent::assertValid($attributes);
    }

    /** Validate before casting: PHP casts true and non-empty arrays to group 1. */
    public static function actionGroupId(mixed $actions): ?int
    {
        if ($actions === null) {
            return null;
        }

        if (! is_array($actions) || array_diff(array_keys($actions), ['add_to_group', 'send_notification'])) {
            throw new ValidationException(['actions' => 'Actions must contain only supported action keys.']);
        }

        if (array_key_exists('send_notification', $actions) && ! is_bool($actions['send_notification'])) {
            throw new ValidationException(['actions' => 'send_notification must be a boolean.']);
        }

        $id = $actions['add_to_group'] ?? null;
        if ($id === null) {
            return null;
        }

        if ((! is_int($id) && ! (is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id)))
            || filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id < 1
            || in_array((int) $id, [Group::ADMINISTRATOR_ID, Group::GUEST_ID, Group::MEMBER_ID], true)) {
            throw new ValidationException(['actions' => 'add_to_group must be a non-protected positive group ID.']);
        }

        return (int) $id;
    }

    public static function assertCanChangeActions(User $actor, mixed $actions, mixed $previous = null, bool $eligibilityChanged = false): void
    {
        $group = is_array($actions) ? ($actions['add_to_group'] ?? null) : null;
        $oldGroup = is_array($previous) ? ($previous['add_to_group'] ?? null) : null;

        // Changing eligibility can otherwise turn an admin-configured action into a manual grant.
        if ($group !== $oldGroup || ($eligibilityChanged && ($group !== null || $oldGroup !== null))) {
            $actor->assertAdmin();
        }
    }

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
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'slug' => [
                'sometimes',
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
            'actions' => ['nullable', 'array'],
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
