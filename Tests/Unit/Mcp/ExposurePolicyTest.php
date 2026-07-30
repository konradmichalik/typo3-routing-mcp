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

namespace KonradMichalik\Typo3RoutingMcp\Tests\Unit\Mcp;

use KonradMichalik\Ttt\Attribute\WithEnvironment;
use KonradMichalik\Typo3Routing\Routing\RouteRegistry;
use KonradMichalik\Typo3RoutingMcp\Mcp\ExposurePolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * ExposurePolicyTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ExposurePolicyTest extends TestCase
{
    #[Test]
    public function exposesARouteWithNoExclusionAndNoEnvRestriction(): void
    {
        $policy = $this->policy(
            ['course_show' => ['path' => '/api/courses/{id}', 'methods' => ['GET'], 'controller' => 'ctrl::show', 'env' => null, 'requirements' => []]],
            ['course_show' => ['name' => 'course_show', 'description' => null, 'readOnly' => false, 'excludedReason' => null]],
        );

        self::assertTrue($policy->isExposed('course_show'));
        self::assertSame(['name' => 'course_show', 'description' => null, 'readOnly' => false], $policy->toolConfig('course_show'));
    }

    #[Test]
    public function excludesARouteWithAnExcludedReason(): void
    {
        $policy = $this->policy(
            ['secure' => ['path' => '/api/secure', 'methods' => ['GET'], 'controller' => 'ctrl::secure', 'env' => null, 'requirements' => []]],
            ['secure' => ['name' => 'secure', 'description' => null, 'readOnly' => false, 'excludedReason' => 'Guarded by an authenticator.']],
        );

        self::assertFalse($policy->isExposed('secure'));
        self::assertNull($policy->toolConfig('secure'));
    }

    #[Test]
    public function excludesARouteNotCarryingMcpToolAtAll(): void
    {
        $policy = $this->policy(
            ['plain' => ['path' => '/api/plain', 'methods' => ['GET'], 'controller' => 'ctrl::plain', 'env' => null, 'requirements' => []]],
            [],
        );

        self::assertFalse($policy->isExposed('plain'));
        self::assertNull($policy->toolConfig('plain'));
    }

    #[Test]
    #[WithEnvironment(context: 'Production')]
    public function excludesARouteBoundToADifferentEnvironment(): void
    {
        $policy = $this->policy(
            ['dev_only' => ['path' => '/api/dev', 'methods' => ['GET'], 'controller' => 'ctrl::dev', 'env' => 'Development', 'requirements' => []]],
            ['dev_only' => ['name' => 'dev_only', 'description' => null, 'readOnly' => false, 'excludedReason' => null]],
        );

        self::assertFalse($policy->isExposed('dev_only'));
    }

    #[Test]
    #[WithEnvironment(context: 'Development')]
    public function exposesARouteMatchingTheCurrentEnvironmentCaseInsensitively(): void
    {
        $policy = $this->policy(
            ['dev_only' => ['path' => '/api/dev', 'methods' => ['GET'], 'controller' => 'ctrl::dev', 'env' => 'development', 'requirements' => []]],
            ['dev_only' => ['name' => 'dev_only', 'description' => null, 'readOnly' => false, 'excludedReason' => null]],
        );

        self::assertTrue($policy->isExposed('dev_only'));
    }

    #[Test]
    #[WithEnvironment(context: 'Development/Docker')]
    public function exposesARouteMatchingOnlyTheFirstContextSegment(): void
    {
        $policy = $this->policy(
            ['dev_only' => ['path' => '/api/dev', 'methods' => ['GET'], 'controller' => 'ctrl::dev', 'env' => 'Development', 'requirements' => []]],
            ['dev_only' => ['name' => 'dev_only', 'description' => null, 'readOnly' => false, 'excludedReason' => null]],
        );

        self::assertTrue($policy->isExposed('dev_only'));
    }

    #[Test]
    public function allReturnsEveryEntryExposedOrNot(): void
    {
        $mcpTools = [
            'exposed' => ['name' => 'exposed', 'description' => null, 'readOnly' => false, 'excludedReason' => null],
            'excluded' => ['name' => 'excluded', 'description' => null, 'readOnly' => false, 'excludedReason' => 'reason'],
        ];
        $policy = $this->policy(
            [
                'exposed' => ['path' => '/api/a', 'methods' => ['GET'], 'controller' => 'ctrl::a', 'env' => null, 'requirements' => []],
                'excluded' => ['path' => '/api/b', 'methods' => ['GET'], 'controller' => 'ctrl::b', 'env' => null, 'requirements' => []],
            ],
            $mcpTools,
        );

        self::assertSame($mcpTools, $policy->all());
    }

    /**
     * @param array<string, array{path: string, methods: list<string>, controller: string, env: string|null, requirements: array<string, string>}> $routes
     * @param array<string, array{name: string, description: string|null, readOnly: bool, excludedReason: string|null}>                            $mcpTools
     */
    private function policy(array $routes, array $mcpTools): ExposurePolicy
    {
        return new ExposurePolicy(new RouteRegistry($routes, new ServiceLocator([])), $mcpTools);
    }
}
