<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Config\AppConfig;
use App\Config\ConfigException;
use App\Config\FileMode;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function validConfig(): array
    {
        return [
            'debug' => false,
            'base_path' => '/modify-local-files-web-interface',
            'login_password' => 'secret',
            'session_lifetime_seconds' => 3600,
            'log_level' => 'info',
            'files' => [
                'blacklist' => ['label' => 'Blacklist', 'path' => 'C:\\data\\blacklist.txt', 'mode' => 'edit'],
                'notes' => ['label' => 'Notes', 'path' => '/tmp/notes.txt', 'mode' => 'append'],
            ],
        ];
    }

    public function testValidConfigIsParsedIntoTypedValues(): void
    {
        $config = AppConfig::fromArray(self::validConfig());

        $this->assertFalse($config->debug);
        $this->assertSame('/modify-local-files-web-interface', $config->basePath);
        $this->assertSame('secret', $config->loginPassword);
        $this->assertSame(3600, $config->sessionLifetimeSeconds);
        $this->assertSame(Level::Info, $config->logLevel);
        $this->assertCount(2, $config->files);

        $file = $config->findFile('notes');
        $this->assertNotNull($file);
        $this->assertSame('notes', $file->id);
        $this->assertSame('Notes', $file->label);
        $this->assertSame('/tmp/notes.txt', $file->path);
        $this->assertSame(FileMode::Append, $file->mode);
    }

    public function testUnknownFileIdIsNotFound(): void
    {
        $this->assertNull(AppConfig::fromArray(self::validConfig())->findFile('missing'));
    }

    public function testEmptyFileListIsAllowed(): void
    {
        $raw = self::validConfig();
        $raw['files'] = [];

        $this->assertSame([], AppConfig::fromArray($raw)->files);
    }

    /** @return iterable<string, array{0: callable(array<string, mixed>): array<string, mixed>}> */
    public static function invalidConfigs(): iterable
    {
        yield 'missing password' => [static function (array $c): array {
            unset($c['login_password']);
            return $c;
        }];
        yield 'empty password' => [static fn (array $c): array => ['login_password' => ''] + $c];
        yield 'zero lifetime' => [static fn (array $c): array => ['session_lifetime_seconds' => 0] + $c];
        yield 'base path with trailing slash' => [static fn (array $c): array => ['base_path' => '/app/'] + $c];
        yield 'unknown log level' => [static fn (array $c): array => ['log_level' => 'loud'] + $c];
        yield 'missing files key' => [static function (array $c): array {
            unset($c['files']);
            return $c;
        }];
        yield 'bad file id' => [static function (array $c): array {
            $c['files'] = ['Bad Id' => ['label' => 'x', 'path' => 'x', 'mode' => 'edit']];
            return $c;
        }];
        yield 'invalid mode' => [static function (array $c): array {
            $c['files']['blacklist']['mode'] = 'delete';
            return $c;
        }];
        yield 'empty path' => [static function (array $c): array {
            $c['files']['blacklist']['path'] = '';
            return $c;
        }];
        yield 'missing label' => [static function (array $c): array {
            unset($c['files']['blacklist']['label']);
            return $c;
        }];
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $mutate */
    #[DataProvider('invalidConfigs')]
    public function testInvalidConfigIsRejected(callable $mutate): void
    {
        $this->expectException(ConfigException::class);

        AppConfig::fromArray($mutate(self::validConfig()));
    }

    public function testMissingConfigFileIsRejected(): void
    {
        $this->expectException(ConfigException::class);

        AppConfig::fromFile(__DIR__ . '/does-not-exist.php');
    }
}
