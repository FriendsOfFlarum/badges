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
use Flarum\User\User;
use FoF\Badges\Badge;
use FoF\Badges\Trigger\MetricInterface;
use FoF\Badges\Trigger\MetricManager;
use FoF\Badges\Trigger\TriggerEvaluator;
use Mockery as m;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TriggerEvaluatorTest extends TestCase
{
    protected TriggerEvaluator $evaluator;
    protected MetricManager $metricManager;
    protected MetricInterface $mockMetric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockMetric = m::mock(MetricInterface::class);
        $this->mockMetric->shouldReceive('getType')->andReturn('post_count');
        $this->mockMetric->shouldReceive('getExtensionDependencies')->andReturn([]);

        $extensions = m::mock(ExtensionManager::class);
        $extensions->shouldReceive('isEnabled')->andReturn(true)->byDefault();

        $this->metricManager = new MetricManager($extensions);
        $this->metricManager->register($this->mockMetric);

        $this->evaluator = new TriggerEvaluator($this->metricManager);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    #[Test]
    public function testEvaluateReturnsFalseForBadgeWithoutTriggerConfig(): void
    {
        $user = m::mock(User::class);
        $badge = m::mock(Badge::class);
        $badge->shouldReceive('getAttribute')->with('trigger_config')->andReturn(null);

        $result = $this->evaluator->evaluate($user, $badge);

        $this->assertFalse($result);
    }

    #[Test]
    public function testEvaluateReturnsFalseForBadgeWithEmptyConditions(): void
    {
        $user = m::mock(User::class);
        $badge = m::mock(Badge::class);
        $badge->shouldReceive('getAttribute')->with('trigger_config')->andReturn([
            'conditions' => [],
            'logic' => 'and',
        ]);

        $result = $this->evaluator->evaluate($user, $badge);

        $this->assertFalse($result);
    }

    #[Test]
    public function testEvaluateWithAndLogicAllConditionsMet(): void
    {
        $user = m::mock(User::class);
        $badge = m::mock(Badge::class);

        $badge->shouldReceive('getAttribute')->with('trigger_config')->andReturn([
            'conditions' => [
                ['metric' => 'post_count', 'operator' => '>=', 'value' => 10],
            ],
            'logic' => 'and',
        ]);

        $this->mockMetric->shouldReceive('getValue')
            ->with($user, m::any())
            ->andReturn(15);

        $result = $this->evaluator->evaluate($user, $badge);

        $this->assertTrue($result);
    }

    #[Test]
    public function testEvaluateWithAndLogicNotAllConditionsMet(): void
    {
        $user = m::mock(User::class);
        $badge = m::mock(Badge::class);

        $badge->shouldReceive('getAttribute')->with('trigger_config')->andReturn([
            'conditions' => [
                ['metric' => 'post_count', 'operator' => '>=', 'value' => 100],
            ],
            'logic' => 'and',
        ]);

        $this->mockMetric->shouldReceive('getValue')
            ->with($user, m::any())
            ->andReturn(15);

        $result = $this->evaluator->evaluate($user, $badge);

        $this->assertFalse($result);
    }

    #[Test]
    public function testEvaluateWithOrLogicOneConditionMet(): void
    {
        $user = m::mock(User::class);
        $badge = m::mock(Badge::class);

        $badge->shouldReceive('getAttribute')->with('trigger_config')->andReturn([
            'conditions' => [
                ['metric' => 'post_count', 'operator' => '>=', 'value' => 100],
                ['metric' => 'post_count', 'operator' => '>=', 'value' => 10],
            ],
            'logic' => 'or',
        ]);

        $this->mockMetric->shouldReceive('getValue')
            ->with($user, m::any())
            ->andReturn(15);

        $result = $this->evaluator->evaluate($user, $badge);

        $this->assertTrue($result);
    }

    #[Test]
    public function testCompareOperatorGte(): void
    {
        $this->assertTrue($this->invokeCompare(10, '>=', 10));
        $this->assertTrue($this->invokeCompare(15, '>=', 10));
        $this->assertFalse($this->invokeCompare(5, '>=', 10));
    }

    #[Test]
    public function testCompareOperatorLte(): void
    {
        $this->assertTrue($this->invokeCompare(10, '<=', 10));
        $this->assertTrue($this->invokeCompare(5, '<=', 10));
        $this->assertFalse($this->invokeCompare(15, '<=', 10));
    }

    #[Test]
    public function testCompareOperatorEq(): void
    {
        $this->assertTrue($this->invokeCompare(10, '==', 10));
        $this->assertFalse($this->invokeCompare(5, '==', 10));
    }

    #[Test]
    public function testCompareOperatorGt(): void
    {
        $this->assertTrue($this->invokeCompare(15, '>', 10));
        $this->assertFalse($this->invokeCompare(10, '>', 10));
        $this->assertFalse($this->invokeCompare(5, '>', 10));
    }

    #[Test]
    public function testCompareOperatorLt(): void
    {
        $this->assertTrue($this->invokeCompare(5, '<', 10));
        $this->assertFalse($this->invokeCompare(10, '<', 10));
        $this->assertFalse($this->invokeCompare(15, '<', 10));
    }

    #[Test]
    public function testCompareOperatorNeq(): void
    {
        $this->assertTrue($this->invokeCompare(5, '!=', 10));
        $this->assertFalse($this->invokeCompare(10, '!=', 10));
    }

    protected function invokeCompare(int $value, string $operator, int $target): bool
    {
        $reflection = new \ReflectionClass($this->evaluator);
        $method = $reflection->getMethod('compare');
        $method->setAccessible(true);

        return $method->invoke($this->evaluator, $value, $operator, $target);
    }
}
