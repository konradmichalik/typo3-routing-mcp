<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_routing_mcp" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RoutingMcp\Mcp;

use KonradMichalik\Typo3Routing\Routing\RouteRegistry;

use function array_keys;

/**
 * ToolCatalog.
 *
 * A plain runtime service — no compiler pass needed. Everything it reads (ExposurePolicy's compiled
 * $mcpTools map, RouteRegistry's compiled route/argument data) was already compiled by
 * McpToolCompilerPass in the prior phase.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ToolCatalog
{
    public function __construct(
        private RouteRegistry $registry,
        private ExposurePolicy $exposurePolicy,
        private InputSchemaFactory $schemaFactory,
    ) {}

    /**
     * @return list<ToolDefinition>
     */
    public function list(): array
    {
        $definitions = [];
        foreach (array_keys($this->exposurePolicy->all()) as $routeName) {
            $toolConfig = $this->exposurePolicy->toolConfig($routeName);
            if (null === $toolConfig) {
                continue;
            }

            $route = $this->registry->getRoutes()[$routeName];

            $definitions[] = new ToolDefinition(
                name: $toolConfig['name'],
                description: $toolConfig['description'],
                inputSchema: $this->schemaFactory->build($this->registry->getArguments($routeName), $route['requirements']),
                routeName: $routeName,
                method: $this->firstMethod($route['methods']),
                readOnly: $toolConfig['readOnly'],
            );
        }

        return $definitions;
    }

    /**
     * @param list<string> $methods
     */
    private function firstMethod(array $methods): string
    {
        foreach ($methods as $method) {
            if ('OPTIONS' !== $method) {
                return $method;
            }
        }

        return 'GET';
    }
}
