# AGENTS.md

Guidance for coding agents working in this repository.

## Project overview

TYPO3 extension `typo3_routing_mcp` (`konradmichalik/typo3-routing-mcp`), a satellite of [`konradmichalik/typo3-routing`](https://github.com/konradmichalik/typo3-routing). It exposes routes annotated with `#[McpTool]` as MCP tools over a Streamable HTTP endpoint (`/_mcp` by default, configurable via `endpointPath`), using `mcp/sdk` for protocol and transport. Pre-1.0.

- Extension key: `typo3_routing_mcp`
- PHP: `~8.2 || ~8.3 || ~8.4 || ~8.5`
- TYPO3: `^13.4 || ^14.0`
- Requires `konradmichalik/typo3-routing` `^1.2.0` (`RouteInvoker` and `JsonSchemaMapper` seam)

## Structure

- `Classes/Attribute/McpTool`: opt-in marker on a controller method that already carries `#[Route]`. No attribute, no tool
- `Classes/DependencyInjection/McpToolCompilerPass`: runs after the core `RouteCompilerPass`, collects `#[McpTool]` routes and applies the compile-time exclusions (session-scoped auth, request token)
- `Classes/Mcp/`: `ExposurePolicy` (request-time environment check), `ToolCatalog`, `ToolDefinition`, `InputSchemaFactory` (flat JSON Schema per tool), `ToolInvoker` (maps the route response to an MCP `CallToolResult`)
- `Classes/Http/`: `McpAuthGuard` (bearer-token gate) and `McpEndpointMiddleware` (PSR-15, registered in `Configuration/RequestMiddlewares.php`, builds a fresh `mcp/sdk` server per request)
- `Classes/Command/McpToolsCommand`: `routing:mcp:tools`, lists annotated routes and their exposure status, supports `--json`
- `Configuration/`: services, middleware, TYPO3 configuration
- `Tests/Unit/`, `Tests/Functional/`: PHPUnit tests
- `Tests/Acceptance/Fixtures/`: fixtures, including a sitepackage with sample controllers
- `Tests/CGL/`: isolated Composer project with the code style and analysis tooling

## Development commands

Requires [DDEV](https://ddev.readthedocs.io/en/stable/). Quality tools live in the separate Composer project `Tests/CGL/`, invoked via `ddev cgl` or `composer cgl`.

```bash
ddev start
ddev composer install

ddev cgl lint                  # PHP CS Fixer, EditorConfig, composer-normalize
ddev cgl fix                   # auto-fix
ddev cgl sca                   # PHPStan
ddev cgl migration             # Rector
ddev cgl analyze:dependencies  # missing and unused dependencies

ddev install all               # real TYPO3 13 and 14 instances
ddev mcp-inspect [13|14]       # point the MCP Inspector at /_mcp
```

The EditorConfig step needs `.git` visible in the container. After `git init`, run `ddev restart`.

## Testing

```bash
ddev composer test             # unit tests, no coverage
ddev composer test:coverage    # unit tests with coverage (pcov, no Xdebug needed)
ddev composer test:functional  # functional tests, need the DB environment
```

`phpunit.xml` bootstraps `Tests/Unit/UnitTestsBootstrap.php`, which initializes the TYPO3 `Environment` once per process, and registers `konradmichalik/ttt` as PHPUnit extension. `phpunit.functional.xml` does not register it.

CI runs PHPUnit through a reusable workflow on PHP 8.2 to 8.5, TYPO3 13.4, 14.0 and 14.3, with highest and lowest dependencies. It also runs CGL, a security workflow and OpenSSF Scorecard.

## Code style and static analysis

- PHPStan level 8 with `phpstan-typo3-preset` (`Tests/CGL/phpstan.neon`)
- PHP CS Fixer with `konradmichalik/php-cs-fixer-preset` (`Tests/CGL/.php-cs-fixer.php`)
- Rector for TYPO3 and PHP 8.2 modernization (`Tests/CGL/rector.php`)
- `declare(strict_types=1)` everywhere, `final readonly` where sensible
- Dependencies used directly must be declared in `composer.json`, the dependency analyser flags shadow dependencies

## Git workflow

- Branch from `main`, open a pull request
- Commit format: `<type>: <description>` with type one of feat, fix, refactor, docs, test, chore, perf, ci
- No co-author trailers
