<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Config\FileMode;
use App\Config\ManagedFile;
use App\Files\FileAccessException;
use App\Files\FileConflictException;
use App\Files\FileStore;
use App\Tests\TempDir;
use PHPUnit\Framework\TestCase;

final class FileStoreTest extends TestCase
{
    private TempDir $dir;
    private FileStore $store;

    protected function setUp(): void
    {
        $this->dir = new TempDir();
        $this->store = new FileStore();
    }

    protected function tearDown(): void
    {
        $this->dir->remove();
    }

    private function managed(string $content, FileMode $mode = FileMode::Edit): ManagedFile
    {
        return new ManagedFile('test', 'Test', $this->dir->file('test.txt', $content), $mode);
    }

    public function testReadReturnsContent(): void
    {
        $this->assertSame("a\nb\n", $this->store->read($this->managed("a\nb\n")));
    }

    public function testReadMissingFileThrows(): void
    {
        $this->expectException(FileAccessException::class);

        $this->store->read(new ManagedFile('x', 'X', $this->dir->file('missing.txt'), FileMode::Edit));
    }

    public function testOverwriteReplacesContentWhenHashMatches(): void
    {
        $file = $this->managed("old\n");

        $this->store->overwrite($file, "new\n", $this->store->hash("old\n"));

        $this->assertSame("new\n", file_get_contents($file->path));
    }

    public function testOverwriteRefusesWhenFileChangedMeanwhile(): void
    {
        $file = $this->managed("changed by someone else\n");

        try {
            $this->store->overwrite($file, "mine\n", $this->store->hash("old\n"));
            $this->fail('Expected FileConflictException');
        } catch (FileConflictException $e) {
            $this->assertSame($this->store->hash("changed by someone else\n"), $e->currentHash);
        }
        $this->assertSame("changed by someone else\n", file_get_contents($file->path));
    }

    public function testAppendAddsTextWithTrailingNewline(): void
    {
        $file = $this->managed("a\n", FileMode::Append);

        $this->store->append($file, 'b');

        $this->assertSame("a\nb\n", file_get_contents($file->path));
    }

    public function testAppendSeparatesFromLastLineWithoutNewline(): void
    {
        $file = $this->managed('a', FileMode::Append);

        $this->store->append($file, "b\n");

        $this->assertSame("a\nb\n", file_get_contents($file->path));
    }

    public function testAppendToEmptyFileAddsNoLeadingNewline(): void
    {
        $file = $this->managed('', FileMode::Append);

        $this->store->append($file, 'b');

        $this->assertSame("b\n", file_get_contents($file->path));
    }

    public function testAppendToMissingFileThrows(): void
    {
        $this->expectException(FileAccessException::class);

        $this->store->append(new ManagedFile('x', 'X', $this->dir->file('missing.txt'), FileMode::Append), 'b');
    }
}
