<?php

declare(strict_types=1);

namespace App\Files;

use App\Config\ManagedFile;

/**
 * The only place that reads and writes managed files.
 */
final class FileStore
{
    public function read(ManagedFile $file): string
    {
        $content = is_file($file->path) ? @file_get_contents($file->path) : false;
        if ($content === false) {
            throw new FileAccessException("Cannot read {$file->path}");
        }
        return $content;
    }

    public function hash(string $content): string
    {
        return sha1($content);
    }

    /**
     * Replace the file content, unless it changed since the editor loaded it.
     * The new content is written to a temp file first so a failed write never leaves half a file.
     */
    public function overwrite(ManagedFile $file, string $text, string $expectedHash): void
    {
        $currentHash = $this->hash($this->read($file));
        if (!hash_equals($currentHash, $expectedHash)) {
            throw new FileConflictException($currentHash);
        }

        $tempPath = $file->path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tempPath, $text) === false || !@rename($tempPath, $file->path)) {
            @unlink($tempPath);
            throw new FileAccessException("Cannot write {$file->path}");
        }
    }

    public function append(ManagedFile $file, string $text): void
    {
        $current = $this->read($file);
        $separator = ($current === '' || str_ends_with($current, "\n")) ? '' : "\n";
        $line = str_ends_with($text, "\n") ? $text : $text . "\n";

        if (@file_put_contents($file->path, $separator . $line, FILE_APPEND | LOCK_EX) === false) {
            throw new FileAccessException("Cannot append to {$file->path}");
        }
    }
}
