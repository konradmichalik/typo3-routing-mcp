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

use KonradMichalik\Typo3RoutingMcp\DependencyInjection\McpToolCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    // Priority -10 (lower than the core's implicit 0) guarantees this runs after
    // RouteCompilerPass, whose $routes/$authenticators/$requestTokenScopes
    // arguments this pass reads back — see docs/superpowers/specs/
    // 2026-07-28-mcptool-attribute-exposure-policy-design.md §2.
    $containerBuilder->addCompilerPass(new McpToolCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
};
