<?php

declare(strict_types=1);

namespace App\Config;

use Monolog\Level;

/**
 * Validated application settings loaded from config/app.php.
 */
final readonly class AppConfig
{
    private const FILE_ID_PATTERN = '/^[a-z0-9-]+$/';

    /**
     * @param array<string, ManagedFile> $files keyed by file id
     */
    public function __construct(
        public bool $debug,
        public string $basePath,
        public string $loginPassword,
        public int $sessionLifetimeSeconds,
        public Level $logLevel,
        public array $files,
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new ConfigException("Config file not found: {$path}");
        }
        $raw = require $path;
        if (!is_array($raw)) {
            throw new ConfigException("Config file must return an array: {$path}");
        }
        return self::fromArray($raw);
    }

    /**
     * @param array<mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        $basePath = $raw['base_path'] ?? '';
        if (!is_string($basePath) || ($basePath !== '' && (!str_starts_with($basePath, '/') || str_ends_with($basePath, '/')))) {
            throw new ConfigException("'base_path' must be empty or start with '/' and not end with '/'");
        }

        $lifetime = $raw['session_lifetime_seconds'] ?? null;
        if (!is_int($lifetime) || $lifetime <= 0) {
            throw new ConfigException("'session_lifetime_seconds' must be a positive integer");
        }

        try {
            $logLevel = Level::fromName((string) ($raw['log_level'] ?? 'info'));
        } catch (\UnhandledMatchError) {
            throw new ConfigException("'log_level' must be a PSR-3 level name such as 'info'");
        }

        $files = $raw['files'] ?? null;
        if (!is_array($files)) {
            throw new ConfigException("'files' must be an array");
        }

        return new self(
            debug: ($raw['debug'] ?? false) === true,
            basePath: $basePath,
            loginPassword: self::requireString($raw, 'login_password'),
            sessionLifetimeSeconds: $lifetime,
            logLevel: $logLevel,
            files: self::parseFiles($files),
        );
    }

    public function findFile(string $id): ?ManagedFile
    {
        return $this->files[$id] ?? null;
    }

    /**
     * @param array<mixed> $files
     * @return array<string, ManagedFile>
     */
    private static function parseFiles(array $files): array
    {
        $parsed = [];
        foreach ($files as $id => $file) {
            if (!is_string($id) || preg_match(self::FILE_ID_PATTERN, $id) !== 1) {
                throw new ConfigException("File id '{$id}' must match " . self::FILE_ID_PATTERN);
            }
            if (!is_array($file)) {
                throw new ConfigException("File '{$id}' must be an array");
            }
            $mode = FileMode::tryFrom((string) ($file['mode'] ?? ''));
            if ($mode === null) {
                throw new ConfigException("File '{$id}': 'mode' must be 'edit' or 'append'");
            }
            $parsed[$id] = new ManagedFile(
                $id,
                self::requireString($file, 'label', "File '{$id}': "),
                self::requireString($file, 'path', "File '{$id}': "),
                $mode,
            );
        }
        return $parsed;
    }

    /**
     * @param array<mixed> $raw
     */
    private static function requireString(array $raw, string $key, string $context = ''): string
    {
        $value = $raw[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new ConfigException("{$context}'{$key}' must be a non-empty string");
        }
        return $value;
    }
}
