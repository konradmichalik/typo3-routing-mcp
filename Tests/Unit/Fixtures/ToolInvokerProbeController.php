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

use KonradMichalik\Typo3Routing\Http\HttpProblemException;
use KonradMichalik\Typo3Routing\Routing\RouteControllerInterface;
use TYPO3\CMS\Core\Http\{HtmlResponse, JsonResponse, Response};

/**
 * ToolInvokerProbeController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ToolInvokerProbeController implements RouteControllerInterface
{
    public function success(): JsonResponse
    {
        return new JsonResponse(['count' => 3]);
    }

    public function problem(): never
    {
        throw new HttpProblemException(404, 'Course not found');
    }

    public function nonJson(): HtmlResponse
    {
        return new HtmlResponse('<p>hi</p>');
    }

    public function malformedJson(): Response
    {
        $response = new Response('php://temp', 200, ['Content-Type' => 'application/json']);
        $response->getBody()->write('{not valid json');

        return $response;
    }

    public function emptyBody(): Response
    {
        return new Response('php://temp', 200, ['Content-Type' => 'application/json']);
    }
}
