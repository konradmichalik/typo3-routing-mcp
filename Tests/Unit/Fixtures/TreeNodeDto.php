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

namespace KonradMichalik\Typo3RoutingMcp\Tests\Unit\Fixtures;

/**
 * TreeNodeDto.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class TreeNodeDto
{
    public function __construct(
        public string $label,
        public ?TreeBranchDto $branch = null,
    ) {}
}
