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

namespace KonradMichalik\Typo3RoutingMcp\Tests\Unit\Fixtures;

use KonradMichalik\Typo3Routing\Attribute\Route;
use KonradMichalik\Typo3Routing\Routing\RouteControllerInterface;
use KonradMichalik\Typo3RoutingMcp\Attribute\McpTool;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * McpToolController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class McpToolController implements RouteControllerInterface
{
    #[Route(path: '/api/mcptool/plain', name: 'mcptool_plain')]
    #[McpTool]
    public function plain(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route(path: '/api/mcptool/with-options', name: 'mcptool_with_options')]
    #[McpTool(name: 'custom_name', description: 'Custom description.', readOnly: true)]
    public function withOptions(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route(path: '/api/mcptool/no-tool', name: 'mcptool_no_tool')]
    public function noMcpTool(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
