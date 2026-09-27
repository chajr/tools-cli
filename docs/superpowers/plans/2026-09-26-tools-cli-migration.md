# tools-cli migration to duplicate-detector-lib — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** tools-cli runs on PHP 8.2 / Symfony 7.4 and serves `fs:duplicated` from `bluetree-service/duplicate-detector-lib`.

**Architecture:** Dependencies bumped; the startup path and every command's load-time code are made compatible;
the old `Duplicated` implementation is deleted and the library command is registered in `Commands`. Runtime code
inside tool bodies is not touched unless it breaks at load time.

**Tech Stack:** PHP ^8.2, symfony/console ^7.4, bluetree-service libraries, Docker `chajr/php82-dev` + `redis:latest`.

**Spec:** `docs/superpowers/specs/2026-09-26-tools-cli-migration-design.md`

## Global Constraints

- `php ^8.2`, `symfony/console ^7.4`, `config.platform.php 8.2.0`
- Minimum scope: fix only what breaks at startup, at `list`, or at `<command> --help`
- No tests exist and none are added; verification is the smoke commands below
- Branch `feature/duplicate-detector-lib`, merged to `master`
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

## Review Focus

1. A command whose constructor reads `/etc/toolscli/*.json` must still load when the file exists (env
   `TOOLS_CLI_CONFIG_<name>` override keeps working).
2. Early `return;` inside `execute()` must become `return self::SUCCESS;` — a missed one is a compile error only
   when the file is loaded.
3. Aliases from `etc/alias.ini` pointing at `fs:duplicated` still resolve.
4. The tools list cache (`var/cache`, key `tools`) contains the path of the deleted `DuplicatedFilesTool.php` —
   a stale cache must not break startup.
5. `fs:duplicated -t N` finds `bin/hash-worker.php` from tools-cli's `vendor/`.

Smoke command used by several tasks (`SMOKE`):

```bash
cd /Volumes/Linux/Dropbox/projects/blue/tools-cli && rm -rf var/cache/* && bin/tools-cli list 2>&1 | tail -40
```

---

### Task 1: Dependencies

**Files:**
- Modify: `composer.json`, `composer.lock`

- [ ] **Step 1: Edit `composer.json`**

`require`:

```json
"php": "^8.2",
"ext-redis": "*",
"symfony/filesystem": "^7.4",
"symfony/console": "^7.4",
"symfony/var-dumper": "^7.4",
"bluetree-service/register": "^0.5",
"bluetree-service/container": "^0.3",
"bluetree-service/filesystem": "^0.4",
"bluetree-service/data": "^0.5",
"bluetree-service/symfony-console-style": "^0.7",
"bluetree-service/benchmark": "^0.6",
"bluetree-service/cache": "^0.5",
"bluetree-service/duplicate-detector-lib": "^0.1",
"mikehaertl/php-shellcommand": "^1.7",
"spatie/array-to-xml": "^3.4",
"react/child-process": "^0.6.6",
"ramsey/uuid": "^4.7",
"chajr/grafika": "dev-master"
```

Remove `serafim/pipe`, `bentools/string-combinations`, the whole `require-dev` and `autoload-dev`.
Add `"config": {"platform": {"php": "8.2.0"}}`.

- [ ] **Step 2: Update lock**

Run: `cd /Volumes/Linux/Dropbox/projects/blue/tools-cli && composer update --no-scripts 2>&1 | tail -15`
Expected: `Generating autoload files`, no resolution errors. If a package cannot be resolved, rule on the nearest
compatible constraint and ledger it.

- [ ] **Step 3: Verify versions**

Run: `composer show | grep -E "symfony/console|duplicate-detector-lib|benchmark|register|twig"`
Expected: console 7.4.x, duplicate-detector-lib 0.1.0.0, benchmark 0.6.0.0, register 0.5.x.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "Updated dependencies to PHP 8.2 and Symfony 7.4"
```

### Task 2: execute() signatures

**Files:**
- Modify: every file with `function execute(InputInterface $input, OutputInterface $output)` in `src/`

Symfony 7 declares `execute(...): int`; a child without `: int` is a fatal error when the class loads.

- [ ] **Step 1: Run the transform script** (scratchpad, not committed)

```php
<?php
// fix-execute.php — run from tools-cli root
foreach (explode("\n", trim(shell_exec("grep -rl 'function execute(InputInterface' src"))) as $f) {
    $src = file_get_contents($f);
    $re = '/(function execute\(InputInterface \$input, OutputInterface \$output\))\s*(?::\s*\w+)?(\s*\{)/';
    if (!preg_match($re, $src, $m, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    $start = $m[0][1] + strlen($m[0][0]);
    $depth = 1;
    $i = $start;
    while ($depth) {
        $c = $src[$i++];
        $depth += $c === '{' ? 1 : ($c === '}' ? -1 : 0);
    }
    $body = substr($src, $start, $i - 1 - $start);
    $body = str_replace('return;', 'return self::SUCCESS;', $body);
    if (!preg_match('/return [^;]+;\s*$/', $body)) {
        $body = rtrim($body) . "\n\n        return self::SUCCESS;\n    ";
    }
    $head = $m[1][0] . ': int' . $m[2][0];
    file_put_contents($f, substr($src, 0, $m[0][1]) . $head . $body . substr($src, $i - 1));
}
```

Run: `php <scratchpad>/fix-execute.php && git diff --stat`
Expected: ~27 files changed (not `BackupTool`/`CleanerTool` bodies, they already return 0).

- [ ] **Step 2: Review the diff**

Run: `git diff -U2 src | grep -E "^[+-]" | grep -v "^+++\|^---" | sort | uniq -c`
Expected: only `: int` headers, `return self::SUCCESS;` lines, removed `: void` headers and blank lines. Any
`return;` changed outside an `execute()` body is a bug — fix by hand.

- [ ] **Step 3: Lint**

Run: `for f in $(git diff --name-only); do php -l $f | grep -v "No syntax errors"; done; echo done`
Expected: `done` only.

- [ ] **Step 4: Commit**

```bash
git commit -am "execute() returns int as required by Symfony 7"
```

### Task 3: fs:duplicated from the library

**Files:**
- Delete: `src/Tools/Fs/DuplicatedFilesTool.php`, `src/Tools/Fs/Duplicated/`, `var/tmp/dup/`
- Modify: `src/Console/Commands.php`, `.gitignore`, `bin/tools-cli`

- [ ] **Step 1: Delete the old implementation**

```bash
git rm -rq src/Tools/Fs/DuplicatedFilesTool.php src/Tools/Fs/Duplicated var/tmp/dup
```

Remove the `!var/tmp/dup/.gitkeep` line from `.gitignore`.

- [ ] **Step 2: Register the library command** in `Commands::__construct`, after the `DefaultCommand` line:

```php
$this->set(DuplicatedFilesCommand::class, new DuplicatedFilesCommand('fs:duplicated', [], (string)\getcwd()));
```

with `use BlueDuplicateDetector\Command\DuplicatedFilesCommand;`.

- [ ] **Step 3: Stale tools cache** — `readAllCommandTools()` trusts the cached `file_list`. Skip entries whose
file no longer exists:

```php
foreach ($namespaces['file_list'] as $commandFile) {
    if (!\is_file($commandFile)) {
        continue;
    }
```

- [ ] **Step 4: `bin/tools-cli`** — drop the `try/catch (Twig_Error_*)` around `getFormattedOutput`, keep the call:

```php
$data = Timer::getFormattedOutput('raw+');
```

- [ ] **Step 5: Lint & commit**

Run: `php -l src/Console/Commands.php && php -l bin/tools-cli`
Expected: no syntax errors.

```bash
git add -A src var .gitignore bin
git commit -m "fs:duplicated served by duplicate-detector-lib"
```

### Task 4: Startup and load-time fixes

**Files:**
- Modify: `src/Console/*.php`, `bin/tools-cli`, tool files that fail at load time

- [ ] **Step 1: Run SMOKE** (without clearing cache first once, to exercise Review Focus 4:
`bin/tools-cli list 2>&1 | tail -40`), then SMOKE.
Expected: command list including `fs:duplicated`, no PHP errors/deprecations.

- [ ] **Step 2: Fix each failure at its root and re-run SMOKE** until clean. Known candidates:
`Register`/`SimpleCache`/`Container` constructor or config changes in `Commands`; `Alias::setDefaultCommand`;
`Command::__construct` property types; `dump()` from var-dumper 7. Each non-mechanical fix gets a ledger line.

- [ ] **Step 3: `--help` for every command**

Run:
```bash
for c in $(bin/tools-cli list --raw 2>/dev/null | awk '{print $1}'); do
  out=$(bin/tools-cli $c --help 2>&1) || echo "FAIL $c"; echo "$out" | grep -iE "error|deprecat|warning" | sed "s/^/$c: /"
done; echo done
```
Expected: `done` only. Fix failures, re-run.

- [ ] **Step 4: Commit**

```bash
git add -A src bin
git commit -m "Startup fixes for Symfony 7.4 and new bluetree libraries"
```

### Task 5: fs:duplicated smoke + PHP 8.2

**Files:** none (verification); fixes, if any, go to the files that fail.

`FIX=/Volumes/Linux/Dropbox/projects/blue/duplicate-detector/tests/test-files`

- [ ] **Step 1: Local runs**

```bash
bin/tools-cli fs:duplicated -l $FIX 2>&1 | tail -8
bin/tools-cli fs:duplicated -l -t 2 $FIX 2>&1 | tail -8
bin/tools-cli fs:duplicated -l -t 2 -H <scratchpad>/html $FIX 2>&1 | tail -5; ls <scratchpad>/html
```
Expected: same duplicate counts in `-t 0` and `-t 2`; `index.html` + `duplicates-0001.html` created.

- [ ] **Step 2: PHP 8.2 + Redis in Docker**

```bash
docker network create tcli 2>/dev/null; docker run -d --rm --name tcli-redis --network tcli redis:latest
docker run --rm --network tcli -v /Volumes/Linux/Dropbox/projects/blue:/blue -w /blue/tools-cli \
  -e TOOLS_CLI_CONFIG_config=/blue/tools-cli/etc/config.json \
  -e TOOLS_CLI_CONFIG_cleaner=/blue/tools-cli/etc/cleaner.json \
  -e TOOLS_CLI_CONFIG_wallhaven=/blue/tools-cli/etc/wallhaven.json \
  chajr/php82-dev sh -c "rm -rf var/cache/*; bin/tools-cli list | tail -5; \
  bin/tools-cli fs:duplicated -l -t 2 -r tcli-redis:6379 /blue/duplicate-detector/tests/test-files | tail -8"
docker stop tcli-redis; docker network rm tcli
```
Expected: list works on 8.2; Redis run reports the same counts as Step 1.

- [ ] **Step 3: Commit fixes if any**, then ledger the result.
