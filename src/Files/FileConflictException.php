<?php

declare(strict_types=1);

namespace App\Files;

/**
 * Thrown when a file changed on disk after the editor loaded it.
 */
final class FileConflictException extends \RuntimeException
{
    public function __construct(public readonly string $currentHash)
    {
        parent::__construct('File changed on disk since it was loaded');
    }
}
