# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

TYPO3 CMS extension `routing_mcp` (composer: `konradmichalik/typo3-routing-mcp`). Satellite extension for [`konradmichalik/typo3-routing`](https://github.com/konradmichalik/typo3-routing): exposes routes annotated with `#[McpTool]` as MCP tools over a Streamable HTTP endpoint (`/_mcp`), using `mcp/sdk` for protocol/transport.

**Version 0.x (pre-1.0)**: TYPO3 13-14, PHP 8.2+. Depends on an unreleased `typo3-routing` seam (`RouteInvoker`, `JsonSchemaMapper`) — see `docs/superpowers/specs/2026-07-28-typo3-routing-mcp-design.md` §8.

> The `konradmichalik/typo3-routing` composer requirement currently resolves via a local `path` repository (`../typo3-routing`) because the core package hasn't tagged the `1.0.0` release this extension needs yet. Tighten to `^1.0` and drop the path repository once it has.

## Development Commands

Code quality tools live in a separate Composer project at `Tests/CGL/`. Invoke via `ddev cgl` or `composer cgl`.

### Quality Checks
```bash
ddev cgl lint                 # Run all linters (php-cs-fixer, editorconfig, composer)
ddev cgl fix                  # Fix all automatically fixable issues
ddev cgl sca                  # Run PHPStan (level 8 + phpstan-typo3-preset)
ddev cgl migration            # Run Rector code migrations
ddev cgl analyze:dependencies # Check for missing/unused dependencies
```

### Testing
```bash
ddev composer test            # Unit tests (PHPUnit)
ddev composer test:coverage   # Unit tests + coverage via pcov — NO Xdebug needed (.Build/coverage/)
ddev composer test:functional # Functional tests (needs DB env, see below)
```

Unit tests use [`konradmichalik/ttt`](https://github.com/konradmichalik/ttt) (TYPO3 Testing Terrarium), registered as a PHPUnit extension in `phpunit.xml` only (**not** `phpunit.functional.xml`). `phpunit.xml`'s bootstrap is `Tests/Unit/UnitTestsBootstrap.php` (not plain `vendor/autoload.php`), which initializes TYPO3's `Environment` once for the whole process.

### Development Setup
```bash
ddev start
ddev install all              # Build real TYPO3 v13 + v14 instances under .Build/<version>
ddev install 14                # Build a single version
```

## Code Architecture

This is currently a bare skeleton — see `docs/superpowers/specs/2026-07-28-typo3-routing-mcp-design.md` for the full design (positioning, `#[McpTool]` attribute, `ExposurePolicy`, `ToolCatalog`/`ToolInvoker`, the two core prerequisites this package is blocked on). Update this section as each subsequent implementation plan lands real `Classes/` content.

## Code Quality Standards

- **PHPStan** (`Tests/CGL/phpstan.neon`): level 8 + phpstan-typo3-preset (strict typePerfect rules).
- **PHP CS Fixer** (`Tests/CGL/.php-cs-fixer.php`): konradmichalik preset with file-header docblocks.
- **Rector** (`Tests/CGL/rector.php`): TYPO3 + PHP 8.2 modernization.
- `declare(strict_types=1)` everywhere; `final readonly` where sensible.

## Notes & gotchas

- `ddev cgl` editorconfig step needs `.git` visible in the container; after `git init`, run `ddev restart` (ddev mutagen bind-mounts `/.git` only at start).
- `Resources/Public/Icons/Extension.png` is currently a placeholder copied from `typo3-routing` — replace before the first public release.
- `ddev cgl analyze:dependencies` is expected to fail right now: `Classes/` is still empty, so `konradmichalik/typo3-routing` and `mcp/sdk` have zero usages and `typo3/cms-core` is only referenced from a dev path. This should resolve itself once real `Classes/` implementation code lands — do not add suppressions to `Tests/CGL/composer-dependency-analyser.php` to paper over it.
- The `post-start` DDEV hook's `composer install --ignore-platform-reqs --no-scripts` is expected to fail inside the container with `Source path "../typo3-routing" is not found for package konradmichalik/typo3-routing` — DDEV doesn't mount sibling host directories, so the path repository (see the note above) can't resolve in-container. `ddev start`/`ddev restart` still succeed at the container-topology level; only the hook task fails. Resolves once `typo3-routing` tags `1.0.0` and the path repository is dropped.
- `Tests/CGL/composer.json` pins `"platform": {"php": "8.2"}` (matching root `composer.json`) so its dependency resolution always targets our PHP floor regardless of the installing machine's actual PHP version — without it, a host on PHP 8.4+ can resolve CGL tooling dependencies that use newer PHP syntax and hard-fail under DDEV's real PHP 8.2.
- `ddev mcp-inspect [13|14]` points the MCP Inspector at this package's `/_mcp` endpoint (reads `ROUTING_MCP_BEARER_TOKEN` for the bearer token) — expected to fail (connection refused / 404) until a future plan implements the actual endpoint and at least one `#[McpTool]`-annotated route.
