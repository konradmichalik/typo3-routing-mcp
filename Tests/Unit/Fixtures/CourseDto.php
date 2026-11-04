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
 * CourseDto.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class CourseDto
{
    public function __construct(
        public int $id,
        public string $title,
        public LecturerDto $lecturer,
        public ?string $subtitle = null,
        public ?CourseLevel $level = null,
        public mixed $meta = null,
    ) {}
}
