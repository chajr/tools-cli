# tools-cli — migration to duplicate-detector-lib (minimum)

Date: 2026-09-26

## Goal

Replace the built-in `fs:duplicated` implementation with the command from
`bluetree-service/duplicate-detector-lib`. The library requires `symfony/console ^7.4`, so tools-cli moves to
PHP `^8.2` / Symfony 7.4 with the smallest change that lets the application start and every command load.

Branch `feature/duplicate-detector-lib` from `master`, merged back to `master`.

## Scope — minimum

In:

- dependencies bumped to versions supporting PHP 8.2 / Symfony 7.4;
- startup path (`bin/tools-cli`, `Commands`, `Command`, `Alias`, `DefaultCommand`) working with the new APIs;
- every tool loadable: `Commands` instantiates all `src/Tools/*/*Tool.php` at startup, so each `execute()` gets
  `: int` + `return Command::SUCCESS` (Symfony 7 signature; otherwise a fatal error at class load), and code run
  in tool constructors / `configure()` is fixed where it breaks;
- `fs:duplicated` served by the library.

Out:

- runtime API breaks inside tool bodies (register / container / filesystem / cache / data / style) — fixed only
  when reported;
- tests for existing tools (none exist today);
- `develop` branch sync.

## Dependencies

- `php ^8.2`, `symfony/console ^7.4`, `bluetree-service/duplicate-detector-lib ^0.1`
- `symfony/filesystem ^7.4`, `symfony/var-dumper ^7.4`
- `bluetree-service/register ^0.5`, `container ^0.3`, `filesystem ^0.4`, `data ^0.5`,
  `symfony-console-style ^0.7`, `cache ^0.5`, `benchmark ^0.6` (released as `0.6.0.0` on 2026-09-26)
- `react/child-process ^0.6.6`, `ramsey/uuid ^4`, `ext-redis` kept — used by `SimilarImagesTool`
- `spatie/array-to-xml ^3`, `mikehaertl/php-shellcommand ^1.7`, `chajr/grafika dev-master` kept
- removed: `serafim/pipe`, `bentools/string-combinations` (unused), `require-dev` (`satooshi`, `phpunit 7`,
  `phpmetrics` — no tests)
- `config.platform.php 8.2.0` so the lock installs on 8.2 as well

## fs:duplicated

- delete `src/Tools/Fs/DuplicatedFilesTool.php`, `src/Tools/Fs/Duplicated/`, `var/tmp/dup` (and its `.gitignore`
  line)
- `Commands` registers `new DuplicatedFilesCommand('fs:duplicated', [], getcwd())` next to `DefaultCommand`;
  HTML report defaults to the current directory
- aliases from `etc/alias.ini` keep working (resolved by command name)

## bin/tools-cli

- `Timer` API unchanged in benchmark 0.6; the dead `catch (Twig_Error_*)` (Twig 2 classes) is removed

## Verification

- `php -l` on `src` and `bin/tools-cli`
- `bin/tools-cli list` without errors or deprecations, on local PHP 8.4 and in `chajr/php82-dev`
- `bin/tools-cli <command> --help` for every command
- `bin/tools-cli fs:duplicated` on the library fixtures: `-t 0`, `-t 2`, `-t 2 -r` (Redis available), `-H <dir>`
