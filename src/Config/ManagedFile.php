<?php

declare(strict_types=1);

namespace App\Config;

/**
 * One file from config/app.php that the interface is allowed to change.
 */
final readonly class ManagedFile
{
    public function __construct(
        public string $id,
        public string $label,
        public string $path,
        public FileMode $mode,
    ) {
    }
}
