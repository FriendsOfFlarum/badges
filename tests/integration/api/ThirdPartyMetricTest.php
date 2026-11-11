<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Tests\integration\api;

use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\Badges\Listener\EvaluateBadges;
use FoF\Badges\Trigger\MetricInterface;
use FoF\Badges\Trigger\MetricManager;
use PHPUnit\Framework\Attributes\Test;

class ThirdPartyMetricTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-badges');

        // An extension listens its own event to FoF's listener, as it does for core events.
        $this->extend(
            (new Extend\Event())->listen(ThirdPartyEvent::class, EvaluateBadges::class)
        );

        $this->prepareDatabase([
            'fof_badges' => [
                ['id' => 100, 'name' => 'Collector', 'slug' => 'test-collector', 'description' => 'Collected two things', 'icon' => 'fas fa-box', 'icon_color' => '#ffffff', 'background_color' => '#667eea', 'category_id' => null, 'is_active' => true, 'is_visible' => true, 'order' => 0, 'trigger_config' => '{"conditions":[{"metric":"third_party_count","operator":">=","value":2}],"logic":"AND"}', 'actions' => null],
            ],
            User::class => [
                $this->normalUser(),
            ],
        ]);
    }

    protected function registerMetric(array $dependencies = []): void
    {
        $this->app()->getContainer()->make(MetricManager::class)->register(new ThirdPartyMetric($dependencies));
    }

    protected function dispatch(?int $userId): void
    {
        $user = $userId ? User::query()->findOrFail($userId) : null;

        $this->app()->getContainer()->make('events')->dispatch(new ThirdPartyEvent($user));
    }

    protected function hasBadge(int $userId): bool
    {
        return $this->database()->table('fof_badge_user')->where('user_id', $userId)->where('badge_id', 100)->exists();
    }

    #[Test]
    public function a_third_party_event_awards_through_the_metrics_own_extractor(): void
    {
        $this->registerMetric();
        ThirdPartyMetric::$value = 2;

        $this->dispatch(2);

        $this->assertTrue($this->hasBadge(2));
    }

    #[Test]
    public function nothing_is_awarded_below_the_threshold(): void
    {
        $this->registerMetric();
        ThirdPartyMetric::$value = 1;

        $this->dispatch(2);

        $this->assertFalse($this->hasBadge(2));
    }

    #[Test]
    public function an_extractor_that_finds_no_user_awards_nothing(): void
    {
        $this->registerMetric();
        ThirdPartyMetric::$value = 2;

        $this->dispatch(null);

        $this->assertSame(0, $this->database()->table('fof_badge_user')->count());
    }

    #[Test]
    public function a_metric_whose_extension_is_disabled_is_not_asked(): void
    {
        $this->registerMetric(['acme/not-installed']);
        ThirdPartyMetric::$value = 2;

        $this->dispatch(2);

        $this->assertFalse($this->hasBadge(2));
    }

    #[Test]
    public function admins_get_the_registered_metrics_including_third_party_ones(): void
    {
        $this->registerMetric();

        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 1]));
        $metrics = json_decode((string) $response->getBody(), true)['data']['attributes']['badgeMetrics'];
        $types = array_column($metrics, 'type');

        $this->assertSame(['post_count', 'discussion_count', 'edit_count', 'member_days', 'has_avatar'], array_slice($types, 0, 5));
        $this->assertContains('third_party_count', $types);
        $this->assertSame(
            ['type' => 'third_party_count', 'translationKey' => 'acme.admin.third_party_count', 'boolean' => false, 'configFields' => []],
            $metrics[array_search('third_party_count', $types)]
        );
    }

    #[Test]
    public function unavailable_metrics_are_left_out_of_the_admin_list(): void
    {
        $this->registerMetric(['acme/not-installed']);

        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 1]));
        $types = array_column(json_decode((string) $response->getBody(), true)['data']['attributes']['badgeMetrics'], 'type');

        $this->assertNotContains('third_party_count', $types);
        // fof/user-bio is not enabled in the test forum.
        $this->assertNotContains('has_bio', $types);
    }

    #[Test]
    public function members_and_guests_do_not_get_the_metric_list(): void
    {
        foreach ([2, null] as $userId) {
            $request = $this->request('GET', '/api', $userId ? ['authenticatedAs' => $userId] : []);
            $attributes = json_decode((string) $this->send($request)->getBody(), true)['data']['attributes'];

            $this->assertArrayNotHasKey('badgeMetrics', $attributes);
        }
    }
}

class ThirdPartyEvent
{
    public function __construct(public ?User $user)
    {
    }
}

class ThirdPartyMetric implements MetricInterface
{
    public static int $value = 0;

    public function __construct(protected array $dependencies = [])
    {
    }

    public function getType(): string
    {
        return 'third_party_count';
    }

    public function getTranslationKey(): string
    {
        return 'acme.admin.third_party_count';
    }

    public function getEventTriggers(): array
    {
        return [ThirdPartyEvent::class => fn (ThirdPartyEvent $event) => $event->user];
    }

    public function getValue(User $user, array $config = []): int
    {
        return static::$value;
    }

    public function isBoolean(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [];
    }

    public function getExtensionDependencies(): array
    {
        return $this->dependencies;
    }
}
