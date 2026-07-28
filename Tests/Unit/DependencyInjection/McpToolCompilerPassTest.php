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

        self::assertStringContainsString('user session', $mcpTools['mcptool_frontend_guarded']['excludedReason']);
        self::assertStringContainsString(FrontendUserAuthenticator::class, $mcpTools['mcptool_frontend_guarded']['excludedReason']);
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

        self::assertStringContainsString(BackendUserAuthenticator::class, $mcpTools['mcptool_backend_guarded']['excludedReason']);
    }

    #[Test]
    public function excludesARequestTokenGuardedRoute(): void
    {
        $mcpTools = $this->discoverSilencingWarnings([
            'fixture' => McpToolExcludedController::class,
            FrontendUserAuthenticator::class => FrontendUserAuthenticator::class,
            BackendUserAuthenticator::class => BackendUserAuthenticator::class,
        ]);

        self::assertStringContainsString('request token', $mcpTools['mcptool_request_token_guarded']['excludedReason']);
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
        self::assertStringContainsString('user session', $reason);
        self::assertStringContainsString('request token', $reason);
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
