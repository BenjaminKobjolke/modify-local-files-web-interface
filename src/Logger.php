<?php

declare(strict_types=1);

namespace App;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Project logger. All logging goes through this class; the level is set once from config.
 */
final class Logger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(private readonly MonologLogger $inner)
    {
    }

    public static function create(string $logFile, Level $level): self
    {
        return new self(new MonologLogger('app', [new StreamHandler($logFile, $level)]));
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->inner->log($level, $message, $context);
    }
}
