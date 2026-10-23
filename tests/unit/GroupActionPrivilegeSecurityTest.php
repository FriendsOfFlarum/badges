<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Tests\Unit;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use FoF\Badges\Api\Controller\RecalculateBadgesController;
use FoF\Badges\Badge;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\BadgeValidator;
use FoF\Badges\Service\BadgeRecalculationService;
use FoF\Badges\UserBadge;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;

class GroupActionPrivilegeSecurityTest extends TestCase
{
    public static function invalidActions(): array
    {
        $cases = [false, '', 'group', 4, ['unknown' => true], ['send_notification' => 'false']];
        foreach ([1, '1', 2, '2', 3, '3', true, false, [], [4], 1.9, 4.0, '1e0', '1foo', '01', ' 4', '4.0', 0, -1, '99999999999999999999999999'] as $id) {
            $cases[] = ['add_to_group' => $id];
        }

        return array_map(fn ($actions) => [$actions], $cases);
    }

    /** @dataProvider invalidActions */
    public function test_invalid_actions_rejected_before_award_database_access(mixed $actions): void
    {
        $badge = new Badge();
        $badge->setRawAttributes(['actions' => json_encode($actions, JSON_PRESERVE_ZERO_FRACTION)]);
        $awarder = $this->awarder();
        $this->expectException(ValidationException::class);
        $awarder->award(new User(), $badge);
    }

    /** @dataProvider invalidActions */
    public function test_validator_rejects_invalid_actions_even_empty_strings(mixed $actions): void
    {
        $validator = (new \ReflectionClass(BadgeValidator::class))->newInstanceWithoutConstructor();
        $this->expectException(ValidationException::class);
        $validator->assertValid(['actions' => $actions]);
    }

    public function test_automatic_non_protected_group_and_notification_only_actions_remain_allowed(): void
    {
        foreach ([4, '4', 9] as $id) {
            $badge = new Badge();
            $badge->actions = ['add_to_group' => $id, 'send_notification' => false];
            self::assertSame((int) $id, BadgeValidator::actionGroupId($badge->actions));
            BadgeAwarder::assertCanExecuteActions(new User(), $badge, null);
        }
        self::assertNull(BadgeValidator::actionGroupId(['send_notification' => true]));
        self::assertNull(BadgeValidator::actionGroupId(['add_to_group' => null]));
    }

    public function test_manual_award_without_actor_is_denied_before_database_access(): void
    {
        $badge = new Badge();
        $badge->actions = ['add_to_group' => 4];
        $this->expectException(PermissionDeniedException::class);
        $this->awarder()->award(new User(), $badge, UserBadge::GRANTED_BY_MANUAL);
    }

    public function test_manual_award_checks_core_permission_on_target_before_database_access(): void
    {
        $badge = new Badge();
        $badge->actions = ['add_to_group' => 4];
        $target = new User();
        $actor = $this->createMock(User::class);
        $actor->expects(self::once())->method('assertCan')->with('editGroups', $target)
            ->willThrowException(new PermissionDeniedException());
        $this->expectException(PermissionDeniedException::class);
        $this->awarder()->award($target, $badge, UserBadge::GRANTED_BY_MANUAL, $actor);
    }

    public function test_reapplication_checks_core_permission_on_target(): void
    {
        $badge = new Badge();
        $badge->actions = ['add_to_group' => 4];
        $target = new User();
        $actor = $this->createMock(User::class);
        $actor->expects(self::once())->method('assertCan')->with('editGroups', $target)
            ->willThrowException(new PermissionDeniedException());
        $this->expectException(PermissionDeniedException::class);
        $this->awarder()->executeActionsOnly($target, $badge, $actor);
    }

    public function test_legacy_protected_action_is_denied_during_reapplication(): void
    {
        $badge = new Badge();
        $badge->actions = ['add_to_group' => true];
        $this->expectException(ValidationException::class);
        $this->awarder()->executeActionsOnly(new User(), $badge);
    }

    public function test_group_definition_removal_and_eligibility_changes_require_admin(): void
    {
        $actor = $this->createMock(User::class);
        $actor->expects(self::exactly(4))->method('assertAdmin');
        BadgeValidator::assertCanChangeActions($actor, ['add_to_group' => 4]);
        BadgeValidator::assertCanChangeActions($actor, ['add_to_group' => 5], ['add_to_group' => 4]);
        BadgeValidator::assertCanChangeActions($actor, null, ['add_to_group' => 4]);
        BadgeValidator::assertCanChangeActions($actor, ['add_to_group' => 4], ['add_to_group' => 4], true);
    }

    public function test_notification_only_edit_does_not_require_admin_or_group_permission(): void
    {
        $actor = $this->createMock(User::class);
        $actor->expects(self::never())->method('assertAdmin');
        $actor->expects(self::never())->method('assertCan');
        BadgeValidator::assertCanChangeActions($actor, ['add_to_group' => 4, 'send_notification' => false], ['add_to_group' => 4]);
        $badge = new Badge();
        $badge->actions = ['send_notification' => true];
        BadgeAwarder::assertCanExecuteActions(new User(), $badge, $actor, true);
    }

    public function test_recalculation_requires_moderate_when_revocation_is_possible(): void
    {
        $actor = $this->createMock(User::class);
        $calls = [];
        $actor->method('assertCan')->willReturnCallback(function ($ability) use (&$calls) {
            $calls[] = $ability;
            if ($ability === 'badges.moderate') {
                throw new PermissionDeniedException();
            }
        });
        $controller = new RecalculateBadgesController($this->createMock(Queue::class), $this->createMock(BadgeRecalculationService::class));

        try {
            $controller->handle(RequestUtil::withActor(new ServerRequest(), $actor)->withParsedBody(['userId' => 7]));
            self::fail('Revocation must require moderation permission.');
        } catch (PermissionDeniedException $e) {
            self::assertSame(['badges.giveManually', 'badges.moderate'], $calls);
        }
    }

    public function test_no_revoke_recalculation_preserves_grant_only_access(): void
    {
        $actor = $this->createMock(User::class);
        $actor->expects(self::once())->method('assertCan')->with('badges.giveManually');
        $controller = new class($this->createMock(Queue::class), $this->createMock(BadgeRecalculationService::class)) extends RecalculateBadgesController {
            protected function recalculateSingleUser(User $actor, int $userId, ?int $badgeId, bool $noRevoke, bool $reapplyActions = false): JsonResponse
            {
                return new JsonResponse(['noRevoke' => $noRevoke]);
            }
        };
        $response = $controller->handle(RequestUtil::withActor(new ServerRequest(), $actor)->withParsedBody(['userId' => 7, 'noRevoke' => true]));
        self::assertSame('{"noRevoke":true}', (string) $response->getBody());
    }

    private function awarder(): BadgeAwarder
    {
        return new BadgeAwarder($this->createMock(NotificationSyncer::class), $this->createMock(Dispatcher::class));
    }

    public function test_trusted_automatic_action_attaches_non_protected_group(): void
    {
        $this->withMemoryDatabase(function ($connection) {
            $badge = new Badge();
            $badge->actions = ['add_to_group' => 4];
            $target = new User();
            $target->id = 7;
            $target->setRelation('groups', new \Illuminate\Database\Eloquent\Collection());
            $this->awarder()->executeActionsOnly($target, $badge);
            self::assertSame(1, $connection->table('group_user')->where('user_id', 7)->where('group_id', 4)->count());
            self::assertFalse($target->relationLoaded('groups'));
        });
    }

    public function test_nonexistent_action_group_fails_validation(): void
    {
        $this->withMemoryDatabase(function () {
            $validator = (new \ReflectionClass(BadgeValidator::class))->newInstanceWithoutConstructor();
            $this->expectException(ValidationException::class);
            $validator->assertValid(['actions' => ['add_to_group' => 999]]);
        });
    }

    public function test_recalculation_preflights_all_actions_before_processing_with_or_without_reapply(): void
    {
        $this->withMemoryDatabase(function () {
            foreach ([false, true] as $reapply) {
                $plain = new Badge();
                $plain->actions = ['send_notification' => false];
                $group = new Badge();
                $group->actions = ['add_to_group' => 4];
                $actor = $this->createMock(User::class);
                $actor->expects(self::exactly(2))->method('assertCan')->willReturnCallback(function ($ability, $target = null) {
                    if ($ability === 'editGroups') {
                        self::assertSame(7, (int) $target->id);

                        throw new PermissionDeniedException();
                    }
                    self::assertSame('badges.giveManually', $ability);
                });
                $service = $this->createMock(BadgeRecalculationService::class);
                $service->expects(self::once())->method('getBadgesToEvaluate')->with(null, $reapply)
                    ->willReturn(new \Illuminate\Database\Eloquent\Collection([$plain, $group]));
                $service->expects(self::never())->method('preloadUserBadges');
                $service->expects(self::never())->method('processBadgeForUser');
                $controller = new RecalculateBadgesController($this->createMock(Queue::class), $service);

                try {
                    $controller->handle(RequestUtil::withActor(new ServerRequest(), $actor)->withParsedBody([
                        'userId' => 7, 'noRevoke' => true, 'reapplyActions' => $reapply,
                    ]));
                    self::fail('Group action must require editGroups before processing any badge.');
                } catch (PermissionDeniedException $e) {
                    // Expected; mock expectations also prove no badge was processed.
                }
            }
        });
    }

    private function withMemoryDatabase(callable $test): void
    {
        $previous = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $connection = new \Illuminate\Database\SQLiteConnection(new \PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
        $resolver = new \Illuminate\Database\ConnectionResolver(['security' => $connection]);
        $resolver->setDefaultConnection('security');
        \Illuminate\Database\Eloquent\Model::setConnectionResolver($resolver);

        try {
            $connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY)');
            $connection->statement('CREATE TABLE groups (id INTEGER PRIMARY KEY)');
            $connection->statement('CREATE TABLE group_user (user_id INTEGER, group_id INTEGER)');
            $connection->table('users')->insert(['id' => 7]);
            $connection->table('groups')->insert(['id' => 4]);
            $test($connection);
        } finally {
            if ($previous !== null) {
                \Illuminate\Database\Eloquent\Model::setConnectionResolver($previous);
            } else {
                \Illuminate\Database\Eloquent\Model::unsetConnectionResolver();
            }
        }
    }
}
