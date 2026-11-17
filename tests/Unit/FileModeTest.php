<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Config\FileMode;
use PHPUnit\Framework\TestCase;

/** Pins which file modes expose existing content. */
final class FileModeTest extends TestCase
{
    public function testEditShowsContent(): void
    {
        self::assertTrue(FileMode::Edit->showsContent());
    }

    public function testAppendHidesContent(): void
    {
        self::assertFalse(FileMode::Append->showsContent());
    }
}
