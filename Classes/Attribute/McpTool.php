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

namespace KonradMichalik\Typo3RoutingMcp\Attribute;

use Attribute;

/**
 * McpTool.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class McpTool
{
    /**
     * @param bool|null $destructive overrides the `destructiveHint` derived from the HTTP method (true for DELETE);
     *                               ignored when $readOnly is true
     * @param bool|null $idempotent  overrides the `idempotentHint` derived from the HTTP method (true for PUT and
     *                               DELETE); ignored when $readOnly is true
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public bool $readOnly = false,
        public ?bool $destructive = null,
        public ?bool $idempotent = null,
    ) {}
}
