<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercises command exit codes with fake tools; never refreshes the real graph. */
final class BatchCommandsTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function stages(): iterable
    {
        yield 'success' => ['', 0];
        yield 'refresh failure' => ['update', 17];
        yield 'god nodes failure' => ['god-nodes', 17];
        yield 'query failure' => ['query', 17];
    }

    #[DataProvider('stages')]
    public function testGraphCommandReturnsStageExitCode(string $failedStage, int $expected): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows batch execution requires cmd.exe.');
        }

        $root = sys_get_temp_dir() . '/batch commands ' . bin2hex(random_bytes(6));
        $files = $this->createFixture($root, $failedStage);

        try {
            $status = $this->runWithDeadline($files[3], $files[4], $root);
            self::assertFalse($status['running'], 'Batch command exceeded 10 seconds.');
            $output = (string) file_get_contents($files[4]);
            self::assertSame($expected, $status['exitcode'], $output);
            $this->assertStages($failedStage, $output);
        } finally {
            $this->removeFixture($root, $files);
        }
    }

    /** @return list<string> */
    private function createFixture(string $root, string $failedStage): array
    {
        mkdir($root . '/tools', 0777, true);
        mkdir($root . '/graphify-out');
        $files = [
            $root . '/tools/.tw-run-test.bat',
            $root . '/graphify.cmd',
            $root . '/graphify-out/graph.json',
            $root . '/run.cmd',
            $root . '/output.txt',
        ];
        copy(__DIR__ . '/../../tools/graphify_update.bat', $files[0]);
        file_put_contents($files[1], "@echo off\r\necho STAGE:%1\r\nif \"%1\"==\"$failedStage\" exit /b 17\r\nexit /b 0\r\n");
        file_put_contents($files[2], '{}');
        file_put_contents($files[3], "@echo off\r\nset \"PATH=$root;%PATH%\"\r\ncall \"{$files[0]}\"\r\nexit /b %ERRORLEVEL%\r\n");

        return $files;
    }

    /** @return array{running: bool, exitcode: int} */
    private function runWithDeadline(string $script, string $outputFile, string $cwd): array
    {
        $process = proc_open(
            ['cmd.exe', '/d', '/c', $script],
            [0 => ['pipe', 'r'], 1 => ['file', $outputFile, 'w'], 2 => ['file', $outputFile, 'a']],
            $pipes,
            $cwd,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $deadline = microtime(true) + 10;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);

        if ($status['running']) {
            proc_terminate($process);
        }
        proc_close($process);
        return ['running' => $status['running'], 'exitcode' => $status['exitcode']];
    }

    private function assertStages(string $failedStage, string $output): void
    {
        self::assertStringContainsString('STAGE:update', $output);
        if ($failedStage === 'update') {
            self::assertStringNotContainsString('STAGE:god-nodes', $output);
        } elseif ($failedStage === 'god-nodes') {
            self::assertStringNotContainsString('STAGE:query', $output);
        } else {
            self::assertStringContainsString('STAGE:query', $output);
        }
    }

    /** @param list<string> $files */
    private function removeFixture(string $root, array $files): void
    {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($root . '/tools');
        rmdir($root . '/graphify-out');
        rmdir($root);
    }
}
