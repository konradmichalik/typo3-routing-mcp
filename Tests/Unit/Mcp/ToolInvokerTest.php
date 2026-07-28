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

namespace KonradMichalik\Typo3RoutingMcp\Tests\Unit\Mcp;

use KonradMichalik\Ttt\Http\RequestBuilder;
use KonradMichalik\Typo3Routing\Authentication\AccessGuard;
use KonradMichalik\Typo3Routing\Http\{RouteUrlGenerator, SiteBasePathResolver};
use KonradMichalik\Typo3Routing\Routing\{ControllerArgumentResolver, ControllerInvoker, RouteInvoker, RouteRegistry};
use KonradMichalik\Typo3RoutingMcp\Mcp\ToolInvoker;
use KonradMichalik\Typo3RoutingMcp\Tests\Unit\Fixtures\ToolInvokerProbeController;
use Mcp\Schema\Content\TextContent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * ToolInvokerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ToolInvokerTest extends TestCase
{
    #[Test]
    public function returnsASuccessResultForAJsonResponse(): void
    {
        $result = $this->toolInvoker()->invoke('success', [], $this->request());

        self::assertFalse($result->isError);
        self::assertCount(1, $result->content);
        $content = $result->content[0];
        self::assertInstanceOf(TextContent::class, $content);
        self::assertJsonStringEqualsJsonString('{"count":3}', (string) $content->text);
    }

    #[Test]
    public function returnsTheFullProblemDocumentAsAnErrorResultForAnHttpProblemException(): void
    {
        $result = $this->toolInvoker()->invoke('problem', [], $this->request());

        self::assertTrue($result->isError);
        $content = $result->content[0];
        self::assertInstanceOf(TextContent::class, $content);
        $decoded = json_decode((string) $content->text, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('about:blank', $decoded['type']);
        self::assertSame('Not Found', $decoded['title']);
        self::assertSame(404, $decoded['status']);
        self::assertSame('Course not found', $decoded['detail']);
    }

    #[Test]
    public function throwsForANonJsonResponse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/"nonJson".*Content-Type: text\/html/');

        $this->toolInvoker()->invoke('nonJson', [], $this->request());
    }

    #[Test]
    public function throwsForAResponseLabelledJsonButNotValidJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/malformedJson/i');

        $this->toolInvoker()->invoke('malformedJson', [], $this->request());
    }

    #[Test]
    public function returnsANullContentSuccessResultForAnEmptyJsonBody(): void
    {
        $result = $this->toolInvoker()->invoke('emptyBody', [], $this->request());

        self::assertFalse($result->isError);
        $content = $result->content[0];
        self::assertInstanceOf(TextContent::class, $content);
        self::assertSame('null', $content->text);
    }

    #[Test]
    public function passesArgumentsThroughToTheRoute(): void
    {
        $result = $this->toolInvoker()->invoke('echo', ['id' => 42], $this->request());

        self::assertFalse($result->isError);
        $content = $result->content[0];
        self::assertInstanceOf(TextContent::class, $content);
        self::assertJsonStringEqualsJsonString('{"id":42}', $content->text);
    }

    private function toolInvoker(): ToolInvoker
    {
        return new ToolInvoker($this->routeInvoker());
    }

    private function routeInvoker(): RouteInvoker
    {
        $registry = $this->registry();

        return new RouteInvoker(
            $registry,
            new ControllerInvoker($registry, new ControllerArgumentResolver($this->createMock(PersistenceManagerInterface::class))),
            new AccessGuard($registry, new Context()),
            new RouteUrlGenerator($registry, new SiteBasePathResolver()),
        );
    }

    private function registry(): RouteRegistry
    {
        /** @var array<string, array{path: string, methods: list<string>, controller: string, env: string|null, requirements: array<string, string>}> $routes */
        $routes = [
            'success' => ['path' => '/api/success', 'methods' => ['GET'], 'controller' => 'probe::success', 'env' => null, 'requirements' => []],
            'problem' => ['path' => '/api/problem', 'methods' => ['GET'], 'controller' => 'probe::problem', 'env' => null, 'requirements' => []],
            'nonJson' => ['path' => '/api/non-json', 'methods' => ['GET'], 'controller' => 'probe::nonJson', 'env' => null, 'requirements' => []],
            'malformedJson' => ['path' => '/api/malformed', 'methods' => ['GET'], 'controller' => 'probe::malformedJson', 'env' => null, 'requirements' => []],
            'emptyBody' => ['path' => '/api/empty', 'methods' => ['GET'], 'controller' => 'probe::emptyBody', 'env' => null, 'requirements' => []],
            'echo' => ['path' => '/api/echo/{id}', 'methods' => ['GET'], 'controller' => 'probe::echoId', 'env' => null, 'requirements' => ['id' => '\d+']],
        ];

        /** @var array<string, list<array{name: string, type: string|null, source: string, nullable: bool, hasDefault: bool, default: mixed}>> $arguments */
        $arguments = [
            'success' => [],
            'problem' => [],
            'nonJson' => [],
            'malformedJson' => [],
            'emptyBody' => [],
            'echo' => [['name' => 'id', 'type' => 'int', 'source' => 'path', 'nullable' => false, 'hasDefault' => false, 'default' => null]],
        ];

        $locator = new ServiceLocator([
            'probe' => static fn (): ToolInvokerProbeController => new ToolInvokerProbeController(),
        ]);

        return new RouteRegistry($routes, $locator, arguments: $arguments);
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

    private function request(): ServerRequest
    {
        $site = $this->site();

        return (new RequestBuilder('GET', 'https://example.com/mcp'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage())
            ->build();
    }
}
