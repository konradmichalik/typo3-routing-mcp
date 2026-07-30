<div align="center">

![Extension icon](Resources/Public/Icons/Extension.png)

# TYPO3 extension `typo3_routing_mcp`

[![Latest Stable Version](https://typo3-badges.dev/badge/typo3_routing_mcp/version/shields.svg)](https://extensions.typo3.org/extension/typo3_routing_mcp)
![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014.3-orange.svg)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/typo3-routing-mcp/php?logo=php)](https://packagist.org/packages/konradmichalik/typo3-routing-mcp)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-routing-mcp/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/typo3-routing-mcp/actions/workflows/cgl.yml)
[![Coverage](https://img.shields.io/coverallsCoverage/github/konradmichalik/typo3-routing-mcp?logo=coveralls)](https://coveralls.io/github/konradmichalik/typo3-routing-mcp)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-routing-mcp/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/typo3-routing-mcp/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/konradmichalik/typo3-routing-mcp/license)](LICENSE.md)

</div>

This extension exposes [`typo3-routing`](https://github.com/konradmichalik/typo3-routing) frontend routes as [MCP](https://modelcontextprotocol.io/) tools over a Streamable HTTP endpoint. Add the `#[McpTool]` PHP attribute to a routed controller method, and an AI agent can invoke your project's own domain endpoints directly, not just generic TYPO3 content operations.

> [!NOTE]
> Generic TYPO3 MCP servers can only talk about content: pages, records, files. Your project's own domain endpoints, course search, store locator, whatever your sitepackage needs, are invisible to them. This extension closes that gap: it turns routes you've already declared via `typo3-routing` into MCP tools an agent can call directly.

## ✨ Features

- **One attribute, one tool**: add `#[McpTool]` next to an existing `#[Route]`, flush caches, done
- **Streamable HTTP**: a single endpoint (`/_mcp` by default, configurable), bearer-token gated, usable on staging and production
- **Security by construction**: session-scoped authenticators (`FrontendUserAuthenticator`/`BackendUserAuthenticator`) and request-token-protected routes are never exposed, no matter the attribute

## 🔥 Installation

### Requirements

* TYPO3 >= 13.4
* PHP 8.2+
* <img src="https://github.com/konradmichalik/typo3-routing/blob/main/Resources/Public/Icons/Extension.png?raw=true" width="16" height="16" alt=""> [`konradmichalik/typo3-routing`](https://github.com/konradmichalik/typo3-routing) installed and configured

### Composer

[![Packagist](https://img.shields.io/packagist/v/konradmichalik/typo3-routing-mcp?label=version&logo=packagist)](https://packagist.org/packages/konradmichalik/typo3-routing-mcp)
[![Packagist Downloads](https://img.shields.io/packagist/dt/konradmichalik/typo3-routing-mcp?color=brightgreen)](https://packagist.org/packages/konradmichalik/typo3-routing-mcp)

``` bash
composer require konradmichalik/typo3-routing-mcp
```

### TER

[![TER version](https://typo3-badges.dev/badge/typo3_routing_mcp/version/shields.svg)](https://extensions.typo3.org/extension/typo3_routing_mcp)
[![TER downloads](https://typo3-badges.dev/badge/typo3_routing_mcp/downloads/shields.svg)](https://extensions.typo3.org/extension/typo3_routing_mcp)

Download the zip file from [TYPO3 extension repository (TER)](https://extensions.typo3.org/extension/typo3_routing_mcp).

## 🚀 Quick start

Add `#[McpTool]` next to an existing `#[Route]`:

```php
use KonradMichalik\Typo3Routing\Attribute\Route;
use KonradMichalik\Typo3RoutingMcp\Attribute\McpTool;

#[Route(path: '/api/courses/{id}', name: 'course_show', description: 'Fetch a single course by its numeric ID.')]
#[McpTool]
public function show(int $id): ResponseInterface { /* … */ }
```

Then audit what's exposed, via the TYPO3 console:

```bash
vendor/bin/typo3 routing:mcp:tools
```

Set a bearer token and point an MCP client at the endpoint. Any client that speaks Streamable HTTP works the same way, just configure the URL and the `Authorization` header:

```bash
export ROUTING_MCP_BEARER_TOKEN=<a-long-random-secret>
```

```bash
claude mcp add --transport http my-project \
  https://your-project.example.org/_mcp \
  --header "Authorization: Bearer <a-long-random-secret>"
```

Or, for clients configured via an `mcp.json`-style file (Cursor, VS Code Copilot, and others):

```json
{
  "servers": {
    "my-project": {
      "type": "http",
      "url": "https://your-project.example.org/_mcp",
      "headers": {
        "Authorization": "Bearer <a-long-random-secret>"
      }
    }
  }
}
```

> [!NOTE]
> Without `ROUTING_MCP_BEARER_TOKEN` (or the env var name configured via the extension's `bearerTokenEnvName` setting) set, the endpoint is entirely inactive, not merely unauthenticated.

The `initialize` response also carries an `instructions` field describing how to use the exposed tools (read-only vs. mutating, RFC 9457 error content). Most MCP clients surface this to the connecting agent automatically.

### Configuration

Set these via the extension's TYPO3 Extension Configuration (Admin Tools > Settings > Extension Configuration > `typo3_routing_mcp`):

| Setting | Default | Description |
| --- | --- | --- |
| `bearerTokenEnvName` | `ROUTING_MCP_BEARER_TOKEN` | Name of the process environment variable holding the expected bearer token. |
| `endpointPath` | `/_mcp` | Request path the middleware listens on. Change this if `/_mcp` collides with something else in your project. |

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).
