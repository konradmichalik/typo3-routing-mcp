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

namespace KonradMichalik\Typo3RoutingMcp\Tests\Unit\Http;

use KonradMichalik\Ttt\Attribute\WithEnvVar;
use KonradMichalik\Ttt\Http\Requests;
use KonradMichalik\Typo3Routing\Authentication\{AccessGuard, BearerTokenAuthenticator};
use KonradMichalik\Typo3Routing\Http\{RouteUrlGenerator, SiteBasePathResolver};
use KonradMichalik\Typo3Routing\OpenApi\JsonSchemaMapper;
use KonradMichalik\Typo3Routing\Routing\{ControllerArgumentResolver, ControllerInvoker, RouteInvoker, RouteRegistry};
use KonradMichalik\Typo3RoutingMcp\Http\{McpAuthGuard, McpEndpointMiddleware};
use KonradMichalik\Typo3RoutingMcp\Mcp\{ExposurePolicy, InputSchemaFactory, ToolCatalog, ToolInvoker};
use KonradMichalik\Typo3RoutingMcp\Tests\Unit\Fixtures\ToolInvokerProbeController;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * McpEndpointMiddlewareTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(McpEndpointMiddleware::class)]
final class McpEndpointMiddlewareTest extends TestCase
{
    private const ENV_NAME = 'ROUTING_MCP_TEST_BEARER';
    private const TOKEN = 'test-token';

    #[Test]
    public function delegatesToTheNextHandlerForANonMcpPath(): void
    {
        $downstreamResponse = new Response('php://temp', 204);
        $middleware = $this->middleware();

        $result = $middleware->process(Requests::get('https://example.com/some/other/page')->build(), $this->handler($downstreamResponse));

        self::assertSame($downstreamResponse, $result);
    }

    #[Test]
    public function returnsABareNotFoundWhenNoBearerTokenIsConfigured(): void
    {
        $response = $this->middleware()->process(
            Requests::post('https://example.com/_mcp')->withHeader('Authorization', 'Bearer anything')->build(),
            $this->handler(new Response()),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    #[WithEnvVar(self::ENV_NAME, self::TOKEN)]
    public function returnsABareNotFoundForAWrongBearerToken(): void
    {
        $response = $this->middleware()->process(
            Requests::post('https://example.com/_mcp')->withHeader('Authorization', 'Bearer wrong')->build(),
            $this->handler(new Response()),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    #[WithEnvVar(self::ENV_NAME, self::TOKEN)]
    public function initializeSucceedsAndReturnsASessionIdWithNoStoreCaching(): void
    {
        $response = $this->initialize($this->middleware());

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Mcp-Session-Id'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('"protocolVersion"', (string) $response->getBody());
    }

    #[Test]
    #[WithEnvVar(self::ENV_NAME, self::TOKEN)]
    public function listsToolsInASecondIndependentRequestReusingTheSessionId(): void
    {
        $sessionId = $this->sessionIdFrom($this->initialize($this->middleware()));

        // A fresh middleware instance simulates a second, independent HTTP
        // request/PHP-FPM worker — proving FileSessionStore persistence, not
        // just in-object state shared within this test.
        $response = $this->middleware()->process(
            $this->mcpRequest(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $sessionId),
            $this->handler(new Response()),
        );

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('"success"', $body);

        // The zero-argument "success" tool's inputSchema.properties must serialize as
        // a JSON object ("{}"), not an array ("[]") — Builder::addTool() does not
        // normalize this itself, unlike Tool::fromArray(), so McpEndpointMiddleware
        // must cast it to (object) before registration. json_decode(..., true) would
        // collapse both shapes to an identical PHP [] and hide a regression, so this
        // has to assert on the raw, un-decoded response string instead.
        self::assertStringContainsString('"properties":{}', $body);
    }

    #[Test]
    #[WithEnvVar(self::ENV_NAME, self::TOKEN)]
    public function callsAToolReusingTheSessionIdAndReturnsTheRawArgumentsPassedThrough(): void
    {
        $sessionId = $this->sessionIdFrom($this->initialize($this->middleware()));

        $response = $this->middleware()->process(
            $this->mcpRequest([
                'jsonrpc' => '2.0',
                'id' => 3,
                'method' => 'tools/call',
                'params' => ['name' => 'echo', 'arguments' => ['id' => 42]],
            ], $sessionId),
            $this->handler(new Response()),
        );

        self::assertSame(200, $response->getStatusCode());
        $envelope = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        $toolResultText = $envelope['result']['content'][0]['text'];
        $decoded = json_decode((string) $toolResultText, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(42, $decoded['id']);
    }

    #[Test]
    #[WithEnvVar(self::ENV_NAME, self::TOKEN)]
    public function toolsListStillSucceedsWithANonLocalhostHostHeader(): void
    {
        $sessionId = $this->sessionIdFrom($this->initialize($this->middleware()));

        $response = $this->middleware()->process(
            $this->mcpRequest(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $sessionId)
                ->withHeader('Host', 'staging.example.org'),
            $this->handler(new Response()),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    #[WithEnvVar(self::ENV_NAME, self::TOKEN)]
    public function initializeResultCarriesUsageInstructionsForAgents(): void
    {
        $response = $this->initialize($this->middleware());

        self::assertStringContainsString('"instructions"', (string) $response->getBody());
        self::assertStringContainsString('RFC 9457', (string) $response->getBody());
    }

    #[Test]
    public function delegatesToTheNextHandlerForTheDefaultPathWhenACustomPathIsConfigured(): void
    {
        $downstreamResponse = new Response('php://temp', 204);

        $result = $this->middleware(endpointPath: '/agents/mcp')->process(
            Requests::get('https://example.com/_mcp')->build(),
            $this->handler($downstreamResponse),
        );

        self::assertSame($downstreamResponse, $result);
    }

    #[Test]
    #[WithEnvVar(self::ENV_NAME, self::TOKEN)]
    public function respondsOnTheConfiguredCustomPathInstead(): void
    {
        $response = $this->initialize($this->middleware(endpointPath: '/agents/mcp'), path: '/agents/mcp');

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Mcp-Session-Id'));
    }

    #[Test]
    #[WithEnvVar(self::ENV_NAME, self::TOKEN)]
    public function fallsBackToTheDefaultPathWhenExtensionConfigurationThrowsForEndpointPath(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturnCallback(
            static fn (string $extension, string $path): string => match ($path) {
                'bearerTokenEnvName' => self::ENV_NAME,
                'endpointPath' => throw new RuntimeException('not configured', 2160304167),
                default => '',
            },
        );

        $response = $this->initialize($this->middleware(extensionConfiguration: $extensionConfiguration));

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Mcp-Session-Id'));
    }

    private function initialize(McpEndpointMiddleware $middleware, string $path = '/_mcp'): ResponseInterface
    {
        return $middleware->process(
            $this->mcpRequest([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => [],
                    'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
                ],
            ], path: $path),
            $this->handler(new Response()),
        );
    }

    private function sessionIdFrom(ResponseInterface $response): string
    {
        return $response->getHeaderLine('Mcp-Session-Id');
    }

    /**
     * @param array<string, mixed> $jsonRpcBody
     */
    private function mcpRequest(array $jsonRpcBody, ?string $sessionId = null, string $path = '/_mcp'): ServerRequest
    {
        $site = $this->site();

        $builder = Requests::post('https://example.com'.$path)
            ->withHeader('Authorization', 'Bearer '.self::TOKEN)
            ->withJsonBody($jsonRpcBody)
            // A tools/call route runs the full RouteInvoker chain, which — like
            // ToolInvokerTest's own fixture — needs site/language attributes present
            // on the request (RouteUrlGenerator/SiteBasePathResolver read them).
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());

        if (null !== $sessionId) {
            $builder->withHeader('Mcp-Session-Id', $sessionId);
        }

        return $builder->build();
    }

    private function site(): Site
    {
        return new Site('main', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                ['languageId' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8', 'base' => 'https://example.com/'],
            ],
        ]);
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function middleware(string $endpointPath = '', ?ExtensionConfiguration $extensionConfiguration = null): McpEndpointMiddleware
    {
        $extensionConfiguration ??= $this->defaultExtensionConfiguration($endpointPath);

        $registry = $this->registry();
        $exposurePolicy = new ExposurePolicy($registry, [
            'success' => ['name' => 'success', 'description' => 'Returns a count.', 'readOnly' => true, 'excludedReason' => null],
            'echo' => ['name' => 'echo', 'description' => 'Echoes the given id.', 'readOnly' => true, 'excludedReason' => null],
        ]);
        $catalog = new ToolCatalog($registry, $exposurePolicy, new InputSchemaFactory(new JsonSchemaMapper()));
        $invoker = new ToolInvoker(new RouteInvoker(
            $registry,
            new ControllerInvoker($registry, new ControllerArgumentResolver($this->createMock(PersistenceManagerInterface::class))),
            new AccessGuard($registry, new Context()),
            new RouteUrlGenerator($registry, new SiteBasePathResolver()),
        ));
        $authGuard = new McpAuthGuard(new BearerTokenAuthenticator($extensionConfiguration), $extensionConfiguration);

        return new McpEndpointMiddleware($authGuard, $catalog, $invoker, $extensionConfiguration);
    }

    private function defaultExtensionConfiguration(string $endpointPath): ExtensionConfiguration
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturnMap([
            ['typo3_routing_mcp', 'bearerTokenEnvName', self::ENV_NAME],
            ['typo3_routing_mcp', 'endpointPath', $endpointPath],
        ]);

        return $extensionConfiguration;
    }

    private function registry(): RouteRegistry
    {
        /** @var array<string, array{path: string, methods: list<string>, controller: string, env: string|null, requirements: array<string, string>}> $routes */
        $routes = [
            'success' => ['path' => '/api/success', 'methods' => ['GET'], 'controller' => 'probe::success', 'env' => null, 'requirements' => []],
            'echo' => ['path' => '/api/echo/{id}', 'methods' => ['GET'], 'controller' => 'probe::echoId', 'env' => null, 'requirements' => ['id' => '\d+']],
        ];

        /** @var array<string, list<array{name: string, type: string|null, source: string, nullable: bool, hasDefault: bool, default: mixed}>> $arguments */
        $arguments = [
            'success' => [],
            'echo' => [['name' => 'id', 'type' => 'int', 'source' => 'path', 'nullable' => false, 'hasDefault' => false, 'default' => null]],
        ];

        $locator = new ServiceLocator([
            'probe' => static fn (): ToolInvokerProbeController => new ToolInvokerProbeController(),
        ]);

        return new RouteRegistry($routes, $locator, arguments: $arguments);
    }
}
