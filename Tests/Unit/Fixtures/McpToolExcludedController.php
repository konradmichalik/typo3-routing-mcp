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

use KonradMichalik\Typo3Routing\Attribute\{Authenticate, RequireRequestToken, Route};
use KonradMichalik\Typo3Routing\Authentication\{BackendUserAuthenticator, FrontendUserAuthenticator};
use KonradMichalik\Typo3Routing\Routing\RouteControllerInterface;
use KonradMichalik\Typo3RoutingMcp\Attribute\McpTool;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * McpToolExcludedController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class McpToolExcludedController implements RouteControllerInterface
{
    #[Route(path: '/api/mcptool/frontend-guarded', name: 'mcptool_frontend_guarded')]
    #[McpTool]
    #[Authenticate(FrontendUserAuthenticator::class)]
    public function frontendGuarded(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route(path: '/api/mcptool/backend-guarded', name: 'mcptool_backend_guarded')]
    #[McpTool]
    #[Authenticate(BackendUserAuthenticator::class)]
    public function backendGuarded(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route(path: '/api/mcptool/request-token-guarded', methods: ['POST'], name: 'mcptool_request_token_guarded')]
    #[McpTool]
    #[RequireRequestToken]
    public function requestTokenGuarded(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route(path: '/api/mcptool/both-guarded', methods: ['POST'], name: 'mcptool_both_guarded')]
    #[McpTool]
    #[Authenticate(FrontendUserAuthenticator::class)]
    #[RequireRequestToken]
    public function bothGuarded(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
