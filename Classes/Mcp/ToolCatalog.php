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
use function in_array;

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
        private OutputSchemaFactory $outputSchemaFactory,
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
            $method = $this->firstMethod($route['methods']);
            $readOnly = $toolConfig['readOnly'];

            $definitions[] = new ToolDefinition(
                name: $toolConfig['name'],
                description: $toolConfig['description'],
                inputSchema: $this->schemaFactory->build($this->registry->getArguments($routeName), $route['requirements']),
                routeName: $routeName,
                method: $method,
                readOnly: $readOnly,
                destructive: $this->destructiveHint($readOnly, $toolConfig['destructive'], $method),
                idempotent: $this->idempotentHint($readOnly, $toolConfig['idempotent'], $method),
                outputSchema: $this->outputSchemaFactory->build($this->registry->getReturns($routeName)),
            );
        }

        return $definitions;
    }

    /**
     * The MCP spec gives both hints meaning only when readOnlyHint is false.
     */
    private function destructiveHint(bool $readOnly, ?bool $override, string $method): ?bool
    {
        return $readOnly ? null : ($override ?? ('DELETE' === $method));
    }

    private function idempotentHint(bool $readOnly, ?bool $override, string $method): ?bool
    {
        return $readOnly ? null : ($override ?? in_array($method, ['GET', 'PUT', 'DELETE'], true));
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
