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

use KonradMichalik\Typo3Routing\OpenApi\JsonSchemaMapper;

/**
 * InputSchemaFactory.
 *
 * Wraps the core's JsonSchemaMapper per argument into one flat JSON Schema object — MCP's tool
 * inputSchema has no path/query/body distinction the way OpenAPI does; tools/call's "arguments" is
 * itself a flat key→value bag. See docs/superpowers/specs/
 * 2026-07-29-toolcatalog-inputschemafactory-design.md §2.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class InputSchemaFactory
{
    public function __construct(
        private JsonSchemaMapper $schemas,
    ) {}

    /**
     * @param list<array{name: string, type: string|null, source: string, nullable: bool, hasDefault: bool, default: mixed}> $arguments
     * @param array<string, string>                                                                                          $requirements
     *
     * @return array{type: 'object', properties: array<string, mixed>, required?: list<string>}
     */
    public function build(array $arguments, array $requirements): array
    {
        $properties = [];
        $required = [];

        foreach ($arguments as $argument) {
            if ('request' === $argument['source']) {
                continue;
            }

            $pattern = $requirements[$argument['name']] ?? null;
            $pattern = '' === $pattern ? null : $pattern;
            $schema = $this->schemas->schemaForType($argument['type'], $pattern);

            $properties[$argument['name']] = 'variadic' === $argument['source']
                ? ['type' => 'array', 'items' => $schema]
                : $schema;

            if (!$argument['hasDefault'] && !$argument['nullable']) {
                $required[] = $argument['name'];
            }
        }

        $result = ['type' => 'object', 'properties' => $properties];
        if ([] !== $required) {
            $result['required'] = $required;
        }

        return $result;
    }
}
