<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\FileMode;
use App\Config\TranslationKeys as TK;

/**
 * Validated form body of a save request.
 */
final readonly class FileSavePayload
{
    public const MAX_TEXT_BYTES = 1_000_000;
    private const HASH_PATTERN = '/^[0-9a-f]{40}$/';

    private function __construct(
        public string $text,
        public string $expectedHash,
        public ?string $errorKey,
    ) {
    }

    public static function fromPostArray(mixed $body, FileMode $mode): self
    {
        $text = is_array($body) && is_string($body['text'] ?? null) ? $body['text'] : null;
        $hash = is_array($body) && is_string($body['hash'] ?? null) ? $body['hash'] : '';

        if ($text === null) {
            return new self('', '', TK::ERROR_INVALID_REQUEST);
        }
        // Browser textareas submit CRLF; the files are kept with LF line endings.
        $text = str_replace("\r\n", "\n", $text);

        $errorKey = match (true) {
            strlen($text) > self::MAX_TEXT_BYTES => TK::ERROR_TEXT_TOO_LONG,
            $mode === FileMode::Append && trim($text) === '' => TK::ERROR_TEXT_EMPTY,
            $mode === FileMode::Edit && preg_match(self::HASH_PATTERN, $hash) !== 1 => TK::ERROR_INVALID_REQUEST,
            default => null,
        };

        return new self($text, $mode === FileMode::Edit ? $hash : '', $errorKey);
    }

    public function isValid(): bool
    {
        return $this->errorKey === null;
    }
}
