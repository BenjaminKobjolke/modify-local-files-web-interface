<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\TempDir;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercises the Windows watcher runner with real PHPUnit and isolated fixtures. */
final class TestRunnerTest extends TestCase
{
    /** @return iterable<string, array{bool, int}> */
    public static function results(): iterable
    {
        yield 'passing test' => [true, 0];
        yield 'failing test' => [false, 1];
    }

    #[DataProvider('results')]
    public function testWatcherRunnerPreservesResult(bool $passes, int $expected): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows batch execution requires cmd.exe.');
        }

        $dir = new TempDir();
        $fixture = $dir->file('RunnerFixtureTest.php', $this->fixtureSource($passes));
        $output = $dir->file('output.txt', '');
        $environment = getenv();
        $environment['TICKETS_WATCHER_COMMAND_RUN'] = '1';
        try {
            $process = proc_open(
                ['cmd.exe', '/d', '/c', dirname(__DIR__, 2) . '/tools/tests.bat', '--no-configuration', $fixture],
                [0 => ['pipe', 'r'], 1 => ['file', $output, 'w'], 2 => ['file', $output, 'a']],
                $pipes,
                dirname(__DIR__, 2),
                $environment,
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            $deadline = microtime(true) + 15;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);

            if ($status['running']) {
                exec('taskkill /F /T /PID ' . (int) $status['pid']);
            }
            proc_close($process);
            self::assertFalse($status['running'], 'Fixture runner exceeded 15 seconds.');
            $text = (string) file_get_contents($output);
            self::assertStringNotContainsString('pcntl extension is required', $text);
            self::assertSame($expected, $status['exitcode'], $text);
            self::assertStringContainsString('RunnerFixtureTest::testResult', $text);
        } finally {
            $dir->remove();
        }
    }

    private function fixtureSource(bool $passes): string
    {
        return '<?php
final class RunnerFixtureTest extends \\PHPUnit\\Framework\\TestCase
{
    public function testResult(): void
    {
        self::assertSame("120", ini_get("max_execution_time"));
        self::assertTrue(' . ($passes ? 'true' : 'false') . ');
    }
}
';
    }
}
