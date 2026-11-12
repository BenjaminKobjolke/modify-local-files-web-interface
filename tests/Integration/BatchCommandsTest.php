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

        try {
            $process = proc_open(
                ['cmd.exe', '/d', '/c', $files[3]],
                [0 => ['pipe', 'r'], 1 => ['file', $files[4], 'w'], 2 => ['file', $files[4], 'a']],
                $pipes,
                $root,
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
            self::assertFalse($status['running'], 'Batch command exceeded 10 seconds.');
            $output = (string) file_get_contents($files[4]);
            self::assertSame($expected, $status['exitcode'], $output);
            self::assertStringContainsString('STAGE:update', $output);
            if ($failedStage === 'update') {
                self::assertStringNotContainsString('STAGE:god-nodes', $output);
            } elseif ($failedStage === 'god-nodes') {
                self::assertStringNotContainsString('STAGE:query', $output);
            } else {
                self::assertStringContainsString('STAGE:query', $output);
            }
        } finally {
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
}
