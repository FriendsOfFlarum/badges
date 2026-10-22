<?php

namespace FoF\Badges\Tests\Integration\Api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\Badges\Badge;

class ActionAuthorizationTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-badges');
        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'groups' => [['id' => 100, 'name_singular' => 'Reward', 'name_plural' => 'Rewards']],
            'group_permission' => [['group_id' => 3, 'permission' => 'badges.moderate']],
            'fof_badges' => [[
                'id' => 100, 'name' => 'Group reward', 'slug' => 'group-reward',
                'is_active' => true, 'is_visible' => true,
                'actions' => json_encode(['add_to_group' => 100, 'send_notification' => false]),
                'trigger_config' => json_encode(['logic' => 'AND', 'conditions' => [
                    ['metric' => 'post_count', 'operator' => '>=', 'value' => 50],
                ]]),
            ]],
        ]);
    }

    public function test_admin_actions_survive_create_reload_update_and_clear(): void
    {
        $actions = ['add_to_group' => '100', 'send_notification' => false];
        $body = $this->write('POST', '/api/badges', 1, ['name' => 'New reward', 'actions' => $actions], 201);
        $id = $body['data']['id'];
        $this->assertSame($actions, $body['data']['attributes']['actions']);
        $this->assertSame($actions, Badge::findOrFail($id)->actions);
        $actions['add_to_group'] = 100;
        $actions['send_notification'] = true;
        $body = $this->write('PATCH', '/api/badges/'.$id, 1, ['actions' => $actions], 200, $id);
        $this->assertSame($actions, $body['data']['attributes']['actions']);
        $this->assertSame($actions, Badge::findOrFail($id)->actions);
        $body = $this->write('PATCH', '/api/badges/'.$id, 1, ['actions' => null], 200, $id);
        $this->assertNull($body['data']['attributes']['actions']);
        $this->assertNull(Badge::findOrFail($id)->actions);
    }

    public static function forbiddenChanges(): array
    {
        return [
            'remove group' => [['actions' => null]],
            'remove group key' => [['actions' => ['send_notification' => true]]],
            'disable eligibility' => [['isActive' => false]],
            'clear trigger' => [['triggerConfig' => null]],
            'change trigger' => [['triggerConfig' => ['logic' => 'AND', 'conditions' => [
                ['metric' => 'post_count', 'operator' => '>=', 'value' => 0],
            ]]]],
        ];
    }

    /** @dataProvider forbiddenChanges */
    public function test_moderator_cannot_change_group_action_eligibility(array $attributes): void
    {
        $before = $this->database()->table('fof_badges')->find(100);
        $body = $this->write('PATCH', '/api/badges/100', 2, $attributes, 403, '100');
        $this->assertNotEmpty($body['errors']);
        $this->assertEquals($before, $this->database()->table('fof_badges')->find(100));
    }

    public function test_moderator_can_edit_metadata_and_notification_without_changing_group(): void
    {
        $actions = ['add_to_group' => 100, 'send_notification' => true];
        $body = $this->write('PATCH', '/api/badges/100', 2, ['name' => 'Renamed', 'actions' => $actions], 200, '100');
        $this->assertSame('Renamed', $body['data']['attributes']['name']);
        $this->assertSame($actions, Badge::findOrFail(100)->actions);
    }

    public function test_moderator_cannot_create_group_action(): void
    {
        $before = $this->database()->table('fof_badges')->count();
        $this->write('POST', '/api/badges', 2, ['name' => 'Escalation', 'actions' => ['add_to_group' => 100]], 403);
        $this->assertSame($before, $this->database()->table('fof_badges')->count());
    }

    public static function malformedActions(): array
    {
        return [
            'boolean group' => [['add_to_group' => true]],
            'array group' => [['add_to_group' => [100]]],
            'admin group' => [['add_to_group' => 1]],
            'guest group' => [['add_to_group' => 2]],
            'member group' => [['add_to_group' => 3]],
            'missing group' => [['add_to_group' => 99999]],
            'fraction' => [['add_to_group' => 100.5]],
            'notification string' => [['send_notification' => 'false']],
            'unknown action' => [['unexpected' => true]],
        ];
    }

    /** @dataProvider malformedActions */
    public function test_invalid_actions_fail_before_database_mutation(array $actions): void
    {
        $before = $this->database()->table('fof_badges')->find(100);
        $body = $this->write('PATCH', '/api/badges/100', 1, ['actions' => $actions], 422, '100');
        $this->assertNotEmpty($body['errors']);
        $this->assertEquals($before, $this->database()->table('fof_badges')->find(100));
    }

    private function write(string $method, string $url, int $actor, array $attributes, int $status, ?string $id = null): array
    {
        $data = ['type' => 'badges', 'attributes' => $attributes];
        if ($id !== null) {
            $data['id'] = $id;
        }
        $response = $this->send($this->request($method, $url, [
            'authenticatedAs' => $actor, 'json' => ['data' => $data],
        ]));
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($status, $response->getStatusCode(), json_encode($body));

        return $body;
    }
}
