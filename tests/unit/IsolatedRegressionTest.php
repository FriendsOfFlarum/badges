<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Badges\Tests\unit;

use PHPUnit\Framework\TestCase;

// Also register the extension when the runner overrides the XML bootstrap.
require_once dirname(__DIR__).'/bootstrap.php';

class IsolatedRegressionTest extends TestCase
{
    public function test_optimizer_preserves_holders_and_candidate_filters(): void
    {
        $this->runRegression('BadgeRecalculationOptimizerRegressionTest.php', 'Optimizer regression checks passed: 12');
    }

    public function test_visibility_uses_actual_core_serialization(): void
    {
        $this->runRegression('VisibilityRegressionTest.php', 'Visibility regression checks passed: 20');
    }

    public function test_default_translations_use_core_actor_locale_and_fallback(): void
    {
        $this->runRegression('default-badge-translations.php', 'Default badge translation checks passed (actor locale, 26 fields, partial/full fallback, configuration).');
    }

    private function runRegression(string $script, string $expectedOutput): void
    {
        // Fresh PHP processes isolate model doubles, the container, preferences,
        // and Eloquent's resolver. Scripts use SQLite :memory: or model doubles.
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=1', '-d', 'log_errors=0', dirname(__DIR__).'/'.$script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            dirname(__DIR__, 2)
        );
        if (! is_resource($process)) {
            self::fail('Could not start isolated regression: '.$script);
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($process);

        self::assertSame(0, $status, $script." failed:\n".$output);
        // Exact totals also catch accidentally skipped core callbacks/endpoints.
        self::assertSame($expectedOutput, trim($output), $script);
    }
}
