<?php

declare(strict_types=1);

/*
 * This file is part of the "routing_mcp" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RoutingMcp\Mcp;

use KonradMichalik\Typo3Routing\Routing\RouteRegistry;
use TYPO3\CMS\Core\Core\Environment;

use function explode;
use function strtolower;

/**
 * ExposurePolicy.
 *
 * Compile-time exclusion (session-scoped auth, request token) already happened
 * in McpToolCompilerPass — $mcpTools' excludedReason reflects that. The only
 * check left for runtime is the environment match, mirroring
 * ControllerInvoker::isVisibleInCurrentContext() exactly (case-insensitive match
 * against the first "/"-separated segment of the current TYPO3 context).
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ExposurePolicy
{
    public function __construct(
        private RouteRegistry $registry,
        /** @var array<string, array{name: string, description: string|null, readOnly: bool, excludedReason: string|null}> */
        private array $mcpTools,
    ) {}

    public function isExposed(string $routeName): bool
    {
        $entry = $this->mcpTools[$routeName] ?? null;
        if (null === $entry || null !== $entry['excludedReason']) {
            return false;
        }

        return $this->envMatches($routeName);
    }

    /**
     * @return array{name: string, description: string|null, readOnly: bool}|null
     */
    public function toolConfig(string $routeName): ?array
    {
        if (!$this->isExposed($routeName)) {
            return null;
        }

        $entry = $this->mcpTools[$routeName];

        return [
            'name' => $entry['name'],
            'description' => $entry['description'],
            'readOnly' => $entry['readOnly'],
        ];
    }

    /**
     * @return array<string, array{name: string, description: string|null, readOnly: bool, excludedReason: string|null}>
     */
    public function all(): array
    {
        return $this->mcpTools;
    }

    private function envMatches(string $routeName): bool
    {
        $env = $this->registry->getRoutes()[$routeName]['env'] ?? null;
        if (null === $env || '' === $env) {
            return true;
        }

        $current = explode('/', (string) Environment::getContext())[0];

        return strtolower($current) === strtolower($env);
    }
}
