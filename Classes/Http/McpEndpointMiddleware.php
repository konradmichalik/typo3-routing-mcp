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

namespace KonradMichalik\Typo3RoutingMcp\Http;

use Closure;
use KonradMichalik\Typo3RoutingMcp\Mcp\{ToolCatalog, ToolInvoker};
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\{CorsMiddleware, ProtocolVersionMiddleware};
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Response;

use function is_string;

/**
 * McpEndpointMiddleware.
 *
 * PSR-15 adapter around mcp/sdk's Streamable HTTP transport: intercepts the
 * configured endpoint path (defaults to /_mcp), authenticates via McpAuthGuard,
 * then builds a fresh mcp/sdk Server per request from ToolCatalog::list() and
 * runs it through StreamableHttpTransport.
 *
 * Built fresh every request — not cached — because each registered tool's closure
 * captures the CURRENT request; only the FileSessionStore's directory must stay
 * identical across requests (see docs/superpowers/specs/
 * 2026-07-29-mcpendpointmiddleware-design.md §1a/§3).
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class McpEndpointMiddleware implements MiddlewareInterface
{
    private const DEFAULT_PATH = '/_mcp';

    private const INSTRUCTIONS = <<<'TXT'
        This server exposes project-specific domain routes as MCP tools — each tool
        invokes one #[McpTool]-annotated route. Read a tool's description and
        inputSchema before calling it: arguments are validated against the same
        requirements the underlying HTTP route enforces. Tools annotated read-only
        are safe to call without side effects; others may mutate application state.
        On error, the tool result carries the full RFC 9457 problem document
        (type/title/status/detail/instance) as its content, not just a message —
        inspect `status` to distinguish, for example, a 404 from a 422.
        TXT;

    public function __construct(
        private McpAuthGuard $authGuard,
        private ToolCatalog $toolCatalog,
        private ToolInvoker $toolInvoker,
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->path() !== $request->getUri()->getPath()) {
            return $handler->handle($request);
        }

        if (!$this->authGuard->authenticate($request)) {
            return new Response('php://temp', 404);
        }

        $server = $this->buildServer($request);
        $transport = new StreamableHttpTransport(
            request: $request,
            middleware: [new CorsMiddleware(), new ProtocolVersionMiddleware()],
        );

        return $server->run($transport)->withHeader('Cache-Control', 'no-store');
    }

    /**
     * Resolves this extension's own "endpointPath" setting, not typo3_routing's.
     * Mirrors McpAuthGuard::envName()'s fallback behaviour.
     */
    private function path(): string
    {
        try {
            $configured = $this->extensionConfiguration->get('routing_mcp', 'endpointPath');
            if (is_string($configured) && '' !== $configured) {
                return $configured;
            }
        } catch (Throwable) {
            // Extension not configured yet — fall back to the default path.
        }

        return self::DEFAULT_PATH;
    }

    private function buildServer(ServerRequestInterface $request): Server
    {
        $builder = Server::builder()
            ->setServerInfo('TYPO3 Routing MCP', '1.0.0')
            ->setInstructions(self::INSTRUCTIONS)
            ->setSession(sessionStore: new FileSessionStore(
                Environment::getVarPath().'/routing_mcp/mcp-sessions',
            ));

        foreach ($this->toolCatalog->list() as $tool) {
            $inputSchema = $tool->inputSchema;
            if ([] === $inputSchema['properties']) {
                $inputSchema['properties'] = (object) [];
            }

            $builder->addTool(
                handler: $this->toolHandler($tool->routeName, $request),
                name: $tool->name,
                description: $tool->description,
                inputSchema: $inputSchema,
                annotations: new ToolAnnotations(readOnlyHint: $tool->readOnly),
            );
        }

        return $builder->build();
    }

    /**
     * Bound to ReferenceHandler's own class scope so ReferenceHandler::handle()
     * invokes this closure directly with the raw arguments array instead of
     * reflecting its parameters and matching them by name — the same bypass
     * mcp/sdk's own ExplicitElementLoader uses for handlers of this shape (see
     * design doc §1b).
     */
    private function toolHandler(string $routeName, ServerRequestInterface $request): Closure
    {
        $invoker = $this->toolInvoker;
        $closure = static function (array $arguments) use ($invoker, $routeName, $request): CallToolResult {
            unset($arguments['_session'], $arguments['_request']);

            return $invoker->invoke($routeName, $arguments, $request);
        };

        return Closure::bind($closure, null, ReferenceHandler::class);
    }
}
