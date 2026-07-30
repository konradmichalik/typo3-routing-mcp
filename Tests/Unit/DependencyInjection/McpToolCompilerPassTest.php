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

namespace KonradMichalik\Typo3RoutingMcp\Tests\Unit\DependencyInjection;

use KonradMichalik\Typo3Routing\Authentication\{BackendUserAuthenticator, FrontendUserAuthenticator};
use KonradMichalik\Typo3Routing\DependencyInjection\RouteCompilerPass;
use KonradMichalik\Typo3Routing\Routing\RouteRegistry;
use KonradMichalik\Typo3RoutingMcp\DependencyInjection\McpToolCompilerPass;
use KonradMichalik\Typo3RoutingMcp\Mcp\ExposurePolicy;
use KonradMichalik\Typo3RoutingMcp\Tests\Unit\Fixtures\{McpToolController, McpToolExcludedController};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition};

/**
 * McpToolCompilerPassTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class McpToolCompilerPassTest extends TestCase
{
    #[Test]
    public function exposesARouteCarryingMcpToolWithDefaults(): void
    {
        $mcpTools = $this->discover(['fixture' => McpToolController::class]);

        self::assertSame([
            'name' => 'mcptool_plain',
            'description' => null,
            'readOnly' => false,
            'excludedReason' => null,
        ], $mcpTools['mcptool_plain']);
    }

    #[Test]
    public function honorsCustomNameDescriptionAndReadOnly(): void
    {
        $mcpTools = $this->discover(['fixture' => McpToolController::class]);

        self::assertSame([
            'name' => 'custom_name',
            'description' => 'Custom description.',
            'readOnly' => true,
            'excludedReason' => null,
        ], $mcpTools['mcptool_with_options']);
    }

    #[Test]
    public function ignoresARouteWithoutMcpTool(): void
    {
        $mcpTools = $this->discover(['fixture' => McpToolController::class]);

        self::assertArrayNotHasKey('mcptool_no_tool', $mcpTools);
    }

    #[Test]
    public function excludesAFrontendUserGuardedRouteWithAWarning(): void
    {
        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        }, \E_USER_WARNING);

        try {
            $mcpTools = $this->discover([
                'fixture' => McpToolExcludedController::class,
                FrontendUserAuthenticator::class => FrontendUserAuthenticator::class,
                BackendUserAuthenticator::class => BackendUserAuthenticator::class,
            ]);
        } finally {
            restore_error_handler();
        }

        $reason = $mcpTools['mcptool_frontend_guarded']['excludedReason'];
        self::assertIsString($reason);
        self::assertStringContainsString('user session', $reason);
        self::assertStringContainsString(FrontendUserAuthenticator::class, $reason);
        self::assertNotEmpty($warnings);
        self::assertStringContainsString('mcptool_frontend_guarded', $warnings[0]);
    }

    #[Test]
    public function excludesABackendUserGuardedRoute(): void
    {
        $mcpTools = $this->discoverSilencingWarnings([
            'fixture' => McpToolExcludedController::class,
            FrontendUserAuthenticator::class => FrontendUserAuthenticator::class,
            BackendUserAuthenticator::class => BackendUserAuthenticator::class,
        ]);

        $reason = $mcpTools['mcptool_backend_guarded']['excludedReason'];
        self::assertIsString($reason);
        self::assertStringContainsString(BackendUserAuthenticator::class, $reason);
    }

    #[Test]
    public function excludesARequestTokenGuardedRoute(): void
    {
        $mcpTools = $this->discoverSilencingWarnings([
            'fixture' => McpToolExcludedController::class,
            FrontendUserAuthenticator::class => FrontendUserAuthenticator::class,
            BackendUserAuthenticator::class => BackendUserAuthenticator::class,
        ]);

        $reason = $mcpTools['mcptool_request_token_guarded']['excludedReason'];
        self::assertIsString($reason);
        self::assertStringContainsString('request token', $reason);
    }

    #[Test]
    public function combinesBothExclusionReasons(): void
    {
        $mcpTools = $this->discoverSilencingWarnings([
            'fixture' => McpToolExcludedController::class,
            FrontendUserAuthenticator::class => FrontendUserAuthenticator::class,
            BackendUserAuthenticator::class => BackendUserAuthenticator::class,
        ]);

        $reason = $mcpTools['mcptool_both_guarded']['excludedReason'];
        self::assertIsString($reason);
        self::assertStringContainsString('user session', $reason);
        self::assertStringContainsString('request token', $reason);
    }

    #[Test]
    public function leavesExposurePolicyUntouchedWhenRouteRegistryIsMissing(): void
    {
        $container = new ContainerBuilder();
        $exposurePolicy = new Definition(ExposurePolicy::class);
        $exposurePolicy->setArgument('$mcpTools', ['sentinel' => 'unchanged']);
        $container->setDefinition(ExposurePolicy::class, $exposurePolicy);

        (new McpToolCompilerPass())->process($container);

        self::assertSame(['sentinel' => 'unchanged'], $container->getDefinition(ExposurePolicy::class)->getArgument('$mcpTools'));
    }

    #[Test]
    public function ignoresARouteWhoseControllerServiceIsNotRegistered(): void
    {
        $mcpTools = $this->discoverRawRoute([
            'path' => '/api/ghost', 'methods' => ['GET'], 'controller' => 'ghost_service::method', 'env' => null, 'requirements' => [],
        ]);

        self::assertSame([], $mcpTools);
    }

    #[Test]
    public function ignoresARouteWhoseControllerServiceHasNoResolvableClass(): void
    {
        $mcpTools = $this->discoverRawRoute(
            ['path' => '/api/classless', 'methods' => ['GET'], 'controller' => 'classless_service::method', 'env' => null, 'requirements' => []],
            extraServices: ['classless_service' => null],
        );

        self::assertSame([], $mcpTools);
    }

    #[Test]
    public function ignoresARouteWhoseControllerClassDoesNotExist(): void
    {
        $mcpTools = $this->discoverRawRoute(
            ['path' => '/api/missing-class', 'methods' => ['GET'], 'controller' => 'missing_class_service::method', 'env' => null, 'requirements' => []],
            extraServices: ['missing_class_service' => 'Totally\\Nonexistent\\ClassName'],
        );

        self::assertSame([], $mcpTools);
    }

    /**
     * @param array<string, class-string> $services
     *
     * @return array<string, array{name: string, description: string|null, readOnly: bool, excludedReason: string|null}>
     */
    private function discover(array $services): array
    {
        $container = new ContainerBuilder();

        $registry = new Definition(RouteRegistry::class);
        $registry->setArgument('$routes', []);
        $container->setDefinition(RouteRegistry::class, $registry);

        $exposurePolicy = new Definition(ExposurePolicy::class);
        $exposurePolicy->setArgument('$mcpTools', []);
        $container->setDefinition(ExposurePolicy::class, $exposurePolicy);

        foreach ($services as $id => $class) {
            $definition = new Definition($class);
            $definition->setPublic(false);
            $container->setDefinition($id, $definition);
        }

        (new RouteCompilerPass())->process($container);
        (new McpToolCompilerPass())->process($container);

        /** @var array<string, array{name: string, description: string|null, readOnly: bool, excludedReason: string|null}> $mcpTools */
        $mcpTools = $container->getDefinition(ExposurePolicy::class)->getArgument('$mcpTools');

        return $mcpTools;
    }

    /**
     * Sets the RouteRegistry's $routes argument directly, bypassing RouteCompilerPass,
     * so a single deliberately malformed route can exercise resolveMcpTool()'s
     * container-defensive guards without needing a real #[Route]-attributed fixture.
     *
     * @param array{path: string, methods: list<string>, controller: string, env: string|null, requirements: array<string, string>} $route
     * @param array<string, string|null>                                                                                            $extraServices service id => class, deliberately not class-string since one test passes a non-existent class name
     *
     * @return array<string, array{name: string, description: string|null, readOnly: bool, excludedReason: string|null}>
     */
    private function discoverRawRoute(array $route, array $extraServices = []): array
    {
        $container = new ContainerBuilder();

        $registry = new Definition(RouteRegistry::class);
        $registry->setArgument('$routes', ['probe' => $route]);
        $registry->setArgument('$authenticators', []);
        $registry->setArgument('$requestTokenScopes', []);
        $container->setDefinition(RouteRegistry::class, $registry);

        $exposurePolicy = new Definition(ExposurePolicy::class);
        $exposurePolicy->setArgument('$mcpTools', []);
        $container->setDefinition(ExposurePolicy::class, $exposurePolicy);

        foreach ($extraServices as $id => $class) {
            $container->setDefinition($id, new Definition($class));
        }

        (new McpToolCompilerPass())->process($container);

        /** @var array<string, array{name: string, description: string|null, readOnly: bool, excludedReason: string|null}> $mcpTools */
        $mcpTools = $container->getDefinition(ExposurePolicy::class)->getArgument('$mcpTools');

        return $mcpTools;
    }

    /**
     * @param array<string, class-string> $services
     *
     * @return array<string, array{name: string, description: string|null, readOnly: bool, excludedReason: string|null}>
     */
    private function discoverSilencingWarnings(array $services): array
    {
        set_error_handler(static fn (): bool => true, \E_USER_WARNING);

        try {
            return $this->discover($services);
        } finally {
            restore_error_handler();
        }
    }
}
