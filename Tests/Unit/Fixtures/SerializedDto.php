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

use JsonSerializable;

/**
 * SerializedDto.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class SerializedDto implements JsonSerializable
{
    public function __construct(
        public int $id,
    ) {}

    /**
     * @return array{identifier: int}
     */
    public function jsonSerialize(): array
    {
        return ['identifier' => $this->id];
    }
}
