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

namespace KonradMichalik\Typo3RoutingMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * ExtEmConfTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ExtEmConfTest extends TestCase
{
    public function testExtEmConfIsLoadableAndDeclaresTypo3RoutingDependency(): void
    {
        $rootPath = dirname(__DIR__, 2);

        $EM_CONF = [];
        $_EXTKEY = 'routing_mcp';
        require $rootPath.'/ext_emconf.php';

        /** @var array<string, array{constraints: array{depends: array<string, string>}}> $emConf */
        $emConf = $EM_CONF;

        self::assertArrayHasKey($_EXTKEY, $emConf);
        self::assertArrayHasKey('typo3_routing', $emConf[$_EXTKEY]['constraints']['depends']);
    }
}
