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

use Flarum\Extension\ExtensionManager;
use FoF\Badges\Trigger\MetricInterface;
use FoF\Badges\Trigger\MetricManager;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class MetricManagerTest extends TestCase
{
    protected MetricManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $extensions = m::mock(ExtensionManager::class);
        $extensions->shouldReceive('isEnabled')->andReturn(true)->byDefault();

        $this->manager = new MetricManager($extensions);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /** @test */
    public function testRegisterAndGetMetric(): void
    {
        $metric = m::mock(MetricInterface::class);
        $metric->shouldReceive('getType')->andReturn('test_metric');

        $this->manager->register($metric);
        $result = $this->manager->get('test_metric');

        $this->assertSame($metric, $result);
    }

    /** @test */
    public function testGetReturnsNullForUnknownMetric(): void
    {
        $result = $this->manager->get('unknown_metric');

        $this->assertNull($result);
    }

    /** @test */
    public function testAllReturnsAllMetrics(): void
    {
        $metric1 = m::mock(MetricInterface::class);
        $metric1->shouldReceive('getType')->andReturn('metric_1');

        $metric2 = m::mock(MetricInterface::class);
        $metric2->shouldReceive('getType')->andReturn('metric_2');

        $this->manager->register($metric1);
        $this->manager->register($metric2);

        $all = $this->manager->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('metric_1', $all);
        $this->assertArrayHasKey('metric_2', $all);
    }

    /** @test */
    public function testGetTypesForEventReturnsMetricsForEvent(): void
    {
        $metric1 = m::mock(MetricInterface::class);
        $metric1->shouldReceive('getType')->andReturn('metric_1');
        $metric1->shouldReceive('getEventTriggers')->andReturn([
            'EventA' => fn ($e) => null,
            'EventB' => fn ($e) => null,
        ]);

        $metric2 = m::mock(MetricInterface::class);
        $metric2->shouldReceive('getType')->andReturn('metric_2');
        $metric2->shouldReceive('getEventTriggers')->andReturn([
            'EventB' => fn ($e) => null,
            'EventC' => fn ($e) => null,
        ]);

        $metric3 = m::mock(MetricInterface::class);
        $metric3->shouldReceive('getType')->andReturn('metric_3');
        $metric3->shouldReceive('getEventTriggers')->andReturn([
            'EventC' => fn ($e) => null,
        ]);

        $this->manager->register($metric1);
        $this->manager->register($metric2);
        $this->manager->register($metric3);

        $eventBTypes = $this->manager->getTypesForEvent('EventB');

        $this->assertCount(2, $eventBTypes);
        $this->assertContains('metric_1', $eventBTypes);
        $this->assertContains('metric_2', $eventBTypes);
        $this->assertNotContains('metric_3', $eventBTypes);
    }

    /** @test */
    public function testGetTypesForEventReturnsEmptyForUnknownEvent(): void
    {
        $metric = m::mock(MetricInterface::class);
        $metric->shouldReceive('getType')->andReturn('metric_1');
        $metric->shouldReceive('getEventTriggers')->andReturn([
            'EventA' => fn ($e) => null,
        ]);

        $this->manager->register($metric);

        $result = $this->manager->getTypesForEvent('UnknownEvent');

        $this->assertEmpty($result);
    }

    /** @test */
    public function testIsAvailableReturnsTrueWhenNoDependencies(): void
    {
        $metric = m::mock(MetricInterface::class);
        $metric->shouldReceive('getType')->andReturn('test_metric');
        $metric->shouldReceive('getExtensionDependencies')->andReturn([]);

        $this->manager->register($metric);

        $this->assertTrue($this->manager->isAvailable('test_metric'));
    }

    /** @test */
    public function testIsAvailableReturnsFalseForUnknownMetric(): void
    {
        $this->assertFalse($this->manager->isAvailable('unknown_metric'));
    }
}
