<?php

declare(strict_types=1);

namespace App\Config;

/**
 * How the web interface may change a configured file.
 */
enum FileMode: string
{
    /** Whole content is shown and replaced on save. */
    case Edit = 'edit';
    /** Content stays hidden; submitted text is added at the end. */
    case Append = 'append';

    public function labelKey(): string
    {
        return match ($this) {
            self::Edit => TranslationKeys::MODE_EDIT,
            self::Append => TranslationKeys::MODE_APPEND,
        };
    }

    public function savedMessageKey(): string
    {
        return match ($this) {
            self::Edit => TranslationKeys::FLASH_SAVED,
            self::Append => TranslationKeys::FLASH_APPENDED,
        };
    }
}
