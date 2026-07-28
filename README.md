<div align="center">

![Extension icon](Resources/Public/Icons/Extension.png)

# TYPO3 extension `routing_mcp`

[![Latest Stable Version](https://typo3-badges.dev/badge/routing_mcp/version/shields.svg)](https://extensions.typo3.org/extension/routing_mcp)
![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014.0-orange.svg)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/typo3-routing-mcp/php?logo=php)](https://packagist.org/packages/konradmichalik/typo3-routing-mcp)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-routing-mcp/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/typo3-routing-mcp/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-routing-mcp/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/typo3-routing-mcp/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/konradmichalik/typo3-routing-mcp/license)](LICENSE.md)

</div>

This extension exposes [`typo3-routing`](https://github.com/konradmichalik/typo3-routing) frontend routes as [MCP](https://modelcontextprotocol.io/) tools over a Streamable HTTP endpoint — so an AI agent can invoke your project's own domain endpoints (course search, store locator, whatever lives in your sitepackage), not just generic TYPO3 content operations.

> [!NOTE]
> Nothing is exposed by default. A route becomes callable only when its controller method also carries `#[McpTool]` — opt-in, never opt-out.

## ✨ Features

- **One attribute, one tool** — add `#[McpTool]` next to an existing `#[Route]`, flush caches, done
- **Streamable HTTP** — a single fixed endpoint (`/_mcp`), bearer-token gated, usable on staging and production
- **Security by construction** — session-scoped authenticators (`FrontendUserAuthenticator`/`BackendUserAuthenticator`) and request-token-protected routes are never exposed, no matter the attribute

## 🔥 Installation

### Requirements

* TYPO3 >= 13.4
* PHP 8.2+
* [`konradmichalik/typo3-routing`](https://github.com/konradmichalik/typo3-routing) installed and configured

### Composer

[![Packagist](https://img.shields.io/packagist/v/konradmichalik/typo3-routing-mcp?label=version&logo=packagist)](https://packagist.org/packages/konradmichalik/typo3-routing-mcp)
[![Packagist Downloads](https://img.shields.io/packagist/dt/konradmichalik/typo3-routing-mcp?color=brightgreen)](https://packagist.org/packages/konradmichalik/typo3-routing-mcp)

``` bash
composer require konradmichalik/typo3-routing-mcp
```

### TER

[![TER version](https://typo3-badges.dev/badge/routing_mcp/version/shields.svg)](https://extensions.typo3.org/extension/routing_mcp)
[![TER downloads](https://typo3-badges.dev/badge/routing_mcp/downloads/shields.svg)](https://extensions.typo3.org/extension/routing_mcp)

Download the zip file from [TYPO3 extension repository (TER)](https://extensions.typo3.org/extension/routing_mcp).

## 🚀 Quick start

> [!NOTE]
> This package is under active development — the `#[McpTool]` attribute and the `/_mcp` endpoint are not implemented yet.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).
