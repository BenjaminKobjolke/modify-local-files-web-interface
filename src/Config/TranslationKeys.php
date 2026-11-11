<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Keys of lang/en.json. Use these constants instead of raw key strings.
 */
final class TranslationKeys
{
    public const APP_TITLE = 'app.title';
    public const NAV_FILES = 'nav.files';
    public const NAV_LOGOUT = 'nav.logout';

    public const FILES_SUBTITLE = 'files.subtitle';
    public const FILES_EMPTY = 'files.empty';
    public const FILES_OPEN = 'files.open';

    public const MODE_EDIT = 'mode.edit';
    public const MODE_APPEND = 'mode.append';

    public const EDIT_HINT = 'edit.hint';
    public const EDIT_SUBMIT = 'edit.submit';
    public const APPEND_HINT = 'append.hint';
    public const APPEND_SUBMIT = 'append.submit';
    public const FORM_TEXT_LABEL = 'form.text_label';

    public const FLASH_SAVED = 'flash.saved';
    public const FLASH_APPENDED = 'flash.appended';

    public const ERROR_INVALID_REQUEST = 'error.invalid_request';
    public const ERROR_TEXT_EMPTY = 'error.text_empty';
    public const ERROR_TEXT_TOO_LONG = 'error.text_too_long';
    public const ERROR_CONFLICT = 'error.conflict';
    public const ERROR_FILE_ACCESS = 'error.file_access';

    /**
     * Twig resolves `TK.NAME` through property lookup, not class constants,
     * so expose the constants as virtual properties.
     */
    public function __get(string $name): string
    {
        $constant = self::class . '::' . $name;
        if (!defined($constant)) {
            throw new \LogicException('Unknown translation key: ' . $name);
        }
        return (string) constant($constant);
    }

    public function __isset(string $name): bool
    {
        return defined(self::class . '::' . $name);
    }
}
