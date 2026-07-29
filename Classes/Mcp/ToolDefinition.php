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

/**
 * ToolDefinition.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ToolDefinition
{
    public function __construct(
        public string $name,
        public ?string $description,
        /** @var array{type: 'object', properties: array<string, mixed>, required?: list<string>} */
        public array $inputSchema,
        public string $routeName,
        public string $method,
        public bool $readOnly,
    ) {}
}
