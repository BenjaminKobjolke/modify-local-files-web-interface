# modify-local-files-web-interface

A small password-protected web interface to change local text files from the browser.
The files and what may be done with them are listed in `config/app.php`:

- **edit** — the whole content is shown in a text box; saving replaces the file.
- **append** — the content stays hidden; the text you enter is added at the end of the file.

Typical use: editing `easy-fritz-control`'s `blacklist.txt` from a phone or another PC.

## Requirements

- WAMP (Apache with `mod_rewrite` and `AllowOverride All`) with PHP 8.5
- Composer
- `php-simple-login` and `php-localization` are installed from GitHub (`dev-main`, pinned in
  `composer.lock`; `composer update` pulls their latest commits)

Dependencies: Slim 4, slim/psr7, slim/twig-view (Twig 3), slim/csrf, Monolog 3;
dev: PHPUnit 13, PHPStan 2 (level 5), PHP-CS-Fixer (PSR-12).

## Setup

```bash
composer install
cp config/app.php.example config/app.php
cp tools/tests_config.example.bat tools/tests_config.bat
```

Edit `config/app.php`:

| Key | Meaning |
|---|---|
| `debug` | Show error details in the browser. Keep `false` unless debugging locally. |
| `base_path` | URL prefix, e.g. `/modify-local-files-web-interface`; `''` at the web root. |
| `login_password` | The login password. |
| `session_lifetime_seconds` | How long a login stays valid (default 10 years). |
| `log_level` | PSR-3 level for `var/logs/app.log`. |
| `files` | `id => ['label' => ..., 'path' => ..., 'mode' => 'edit'|'append']`. The id appears in the URL (`[a-z0-9-]`). |

The web server user must be able to write `var/sessions/`, `var/logs/` and every configured file
(for `edit` mode also its folder, because saving writes a temp file next to it and renames it).

## Usage

Open `http://localhost/modify-local-files-web-interface/`, log in, pick a file.

- Edit mode: if the file changed on disk after you opened it (for example the sync tool rewrote
  it), saving is refused and your text is kept in the form; save again to replace the file.
- Log out with the link in the header (or `?logout` on any URL).

### Staying logged in

The login is kept as long as possible:

- The session lives in the app's own `var/sessions/` folder, so other apps on the same WAMP
  cannot clean it up.
- The cookie is renewed on every visit. Browsers cap cookies at 400 days, so the login only
  ends after 400 days without any visit, or when you log out.

### Security

- Only `assets/` is served from the project root; every other request goes to `public/index.php`,
  so `config/`, `var/`, `vendor/` and `src/` cannot be downloaded.
- File paths come only from `config/app.php`; the URL only carries the file id.
- Every form is CSRF-protected.

## Tests

```bash
tools\run_tests.bat              # unit tests
tools\run_integration_tests.bat  # Slim app in-process with temp files, no web server needed
tools\tests.bat                  # all
vendor\bin\phpstan analyse
vendor\bin\php-cs-fixer fix --dry-run
```

## Project layout

- `public/index.php` — front controller: config, session, login, Slim app
- `src/Bootstrap/AppFactory.php` — Slim app, Twig, CSRF, routes
- `src/Http/FileController.php` — list / show / save
- `src/Files/FileStore.php` — the only file I/O
- `src/Config/` — config loading and validation, translation keys
- `templates/`, `lang/en.json`, `assets/css/` (`tokens.css` holds all design tokens)

## License

MIT — see `LICENSE`.
