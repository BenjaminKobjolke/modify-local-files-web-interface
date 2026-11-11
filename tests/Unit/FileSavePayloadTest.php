<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Config\FileMode;
use App\Config\TranslationKeys as TK;
use App\Http\FileSavePayload;
use PHPUnit\Framework\TestCase;

final class FileSavePayloadTest extends TestCase
{
    private const HASH = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

    public function testValidEditPayloadNormalizesLineEndings(): void
    {
        $payload = FileSavePayload::fromPostArray(['text' => "a\r\nb\r\n", 'hash' => self::HASH], FileMode::Edit);

        $this->assertTrue($payload->isValid());
        $this->assertSame("a\nb\n", $payload->text);
        $this->assertSame(self::HASH, $payload->expectedHash);
    }

    public function testEditAllowsEmptyText(): void
    {
        $payload = FileSavePayload::fromPostArray(['text' => '', 'hash' => self::HASH], FileMode::Edit);

        $this->assertTrue($payload->isValid());
    }

    public function testEditRequiresValidHash(): void
    {
        $payload = FileSavePayload::fromPostArray(['text' => 'a', 'hash' => 'nope'], FileMode::Edit);

        $this->assertFalse($payload->isValid());
        $this->assertSame(TK::ERROR_INVALID_REQUEST, $payload->errorKey);
    }

    public function testMissingTextIsInvalid(): void
    {
        $payload = FileSavePayload::fromPostArray(['hash' => self::HASH], FileMode::Edit);

        $this->assertSame(TK::ERROR_INVALID_REQUEST, $payload->errorKey);
        $this->assertSame('', $payload->text);
    }

    public function testNonArrayBodyIsInvalid(): void
    {
        $this->assertFalse(FileSavePayload::fromPostArray(null, FileMode::Append)->isValid());
    }

    public function testOversizeTextIsInvalid(): void
    {
        $text = str_repeat('x', FileSavePayload::MAX_TEXT_BYTES + 1);

        $payload = FileSavePayload::fromPostArray(['text' => $text], FileMode::Append);

        $this->assertSame(TK::ERROR_TEXT_TOO_LONG, $payload->errorKey);
    }

    public function testAppendRejectsBlankText(): void
    {
        $payload = FileSavePayload::fromPostArray(['text' => " \r\n "], FileMode::Append);

        $this->assertSame(TK::ERROR_TEXT_EMPTY, $payload->errorKey);
    }

    public function testAppendIgnoresHash(): void
    {
        $payload = FileSavePayload::fromPostArray(['text' => 'new line'], FileMode::Append);

        $this->assertTrue($payload->isValid());
        $this->assertSame('', $payload->expectedHash);
    }
}
