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

namespace KonradMichalik\Typo3RoutingMcp\Mcp;

use JsonException;
use KonradMichalik\Typo3Routing\Routing\RouteInvoker;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use RuntimeException;
use stdClass;

use function array_is_list;
use function get_object_vars;
use function json_decode;
use function json_encode;
use function sprintf;
use function str_starts_with;

/**
 * ToolInvoker.
 *
 * The handler logic wrapped in a per-route closure and registered with mcp/sdk's
 * Builder::addTool() by a later McpEndpointMiddleware phase — see
 * docs/superpowers/specs/2026-07-28-toolinvoker-design.md.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ToolInvoker
{
    public function __construct(
        private RouteInvoker $invoker,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     */
    public function invoke(string $routeName, array $arguments, ServerRequestInterface $request): CallToolResult
    {
        $response = $this->invoker->invoke($routeName, $arguments, $request);
        $decoded = $this->decodeJsonBody($routeName, $response);

        $content = [new TextContent(json_encode($decoded, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR))];

        if ($response->getStatusCode() >= 400) {
            return CallToolResult::error($content);
        }

        return new CallToolResult($content, structuredContent: $this->structuredContent($decoded));
    }

    /**
     * Only a JSON object qualifies, handed over as the associative array mcp/sdk expects, unless
     * that array would encode as a list (`{}` or keys 0..n): then the object itself is kept.
     *
     * @return array<string, mixed>|stdClass|null
     */
    private function structuredContent(mixed $decoded): array|stdClass|null
    {
        if (!$decoded instanceof stdClass) {
            return null;
        }

        $properties = get_object_vars($decoded);

        return array_is_list($properties) ? $decoded : $properties;
    }

    private function decodeJsonBody(string $routeName, ResponseInterface $response): mixed
    {
        $body = (string) $response->getBody();
        if ('' === $body) {
            return null;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_starts_with($contentType, 'application/json') && !str_starts_with($contentType, 'application/problem+json')) {
            throw new RuntimeException(sprintf('Route "%s" returned a non-JSON response (Content-Type: %s); ToolInvoker requires a JSON body to represent as MCP tool content.', $routeName, '' === $contentType ? '(none)' : $contentType), 1800099552);
        }

        try {
            // Objects stay stdClass so an empty or nested `{}` does not turn into `[]`.
            return json_decode($body, false, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // A key starting with "\u0000" is no valid property name; an array still holds it.
        }

        try {
            return json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('Route "%s" returned a response with Content-Type "%s" but an unparseable body: %s', $routeName, $contentType, $exception->getMessage()), previous: $exception);
        }
    }
}
