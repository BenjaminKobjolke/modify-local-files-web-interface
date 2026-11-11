<?php

declare(strict_types=1);

namespace App\Tests;

/**
 * Per-test scratch directory under the system temp dir, removed again by remove().
 */
final class TempDir
{
    public readonly string $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mlfwi-' . bin2hex(random_bytes(6));
        mkdir($this->path, 0777, true);
    }

    public function file(string $name, ?string $content = null): string
    {
        $path = $this->path . DIRECTORY_SEPARATOR . $name;
        if ($content !== null) {
            file_put_contents($path, $content);
        }
        return $path;
    }

    public function remove(): void
    {
        foreach (scandir($this->path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                unlink($this->path . DIRECTORY_SEPARATOR . $item);
            }
        }
        rmdir($this->path);
    }
}
