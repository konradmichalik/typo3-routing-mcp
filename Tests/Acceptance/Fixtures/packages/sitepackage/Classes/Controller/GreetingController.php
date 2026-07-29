<?php

declare(strict_types=1);

namespace Test\Sitepackage\Controller;

use KonradMichalik\Typo3Routing\Attribute\Route;
use KonradMichalik\Typo3Routing\Routing\RouteControllerInterface;
use KonradMichalik\Typo3RoutingMcp\Attribute\McpTool;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * GreetingController.
 *
 * Demo route for manually testing the /_mcp endpoint end to end (`ddev mcp-inspect`) —
 * not part of the extension's own test suite.
 */
final readonly class GreetingController implements RouteControllerInterface
{
    #[Route(
        path: '/api/greet/{name}',
        name: 'greet',
        requirements: ['name' => '[a-zA-Z]+'],
        description: 'Returns a friendly greeting for the given name.',
    )]
    #[McpTool(description: 'Greet someone by name.', readOnly: true)]
    public function greet(string $name): ResponseInterface
    {
        return new JsonResponse(['message' => sprintf('Hello, %s!', $name)]);
    }
}
