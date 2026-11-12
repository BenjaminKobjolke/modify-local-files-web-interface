<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** Guards the watcher contract: code quality supplements both existing test suites. */
final class AnalyzerSetupTest extends TestCase
{
    public function testWatcherKeepsTestsAndRegistersStrictChangedFilesAnalysis(): void
    {
        $root = __DIR__ . '/../..';
        $watcher = json_decode((string) file_get_contents($root . '/tools/tickets-watcher.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertContains('tools\\run_tests.bat', $watcher['analysis_commands']);
        self::assertContains('tools\\run_integration_tests.bat', $watcher['analysis_commands']);
        $command = 'tools\\analyze_changed_and_new_files.bat';
        self::assertContains($command, $watcher['analysis_commands']);
        self::assertSame('code_quality', $watcher['analysis_command_kinds'][$command]);

        $rules = json_decode((string) file_get_contents($root . '/code_analysis_rules.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('warning', $rules['fail_on']);
        self::assertTrue($rules['phpstan_analyze']['enabled']);
        self::assertTrue($rules['php_cs_fixer']['enabled']);
        self::assertTrue($rules['phpmd_analyze']['enabled']);
        self::assertTrue($rules['php_function_quality']['enabled']);
    }
}
