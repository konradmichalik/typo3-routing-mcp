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

use KonradMichalik\Typo3Routing\OpenApi\JsonSchemaMapper;
use KonradMichalik\Typo3Routing\Routing\RouteRegistry;
use KonradMichalik\Typo3RoutingMcp\Mcp\{ExposurePolicy, InputSchemaFactory, ToolCatalog};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * ToolCatalogTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ToolCatalogTest extends TestCase
{
    #[Test]
    public function listsOnlyExposedRoutesAsToolDefinitions(): void
    {
        $definitions = $this->catalog(
            [
                'course_show' => ['path' => '/api/courses/{id}', 'methods' => ['GET'], 'controller' => 'ctrl::show', 'env' => null, 'requirements' => ['id' => '\d+']],
                'secure_route' => ['path' => '/api/secure', 'methods' => ['GET'], 'controller' => 'ctrl::secure', 'env' => null, 'requirements' => []],
            ],
            [
                'course_show' => ['name' => 'course_show', 'description' => 'Fetch a course.', 'readOnly' => true, 'excludedReason' => null],
                'secure_route' => ['name' => 'secure_route', 'description' => null, 'readOnly' => false, 'excludedReason' => 'Guarded.'],
            ],
            ['course_show' => [['name' => 'id', 'type' => 'string', 'source' => 'path', 'nullable' => false, 'hasDefault' => false, 'default' => null]]],
        )->list();

        self::assertCount(1, $definitions);
        self::assertSame('course_show', $definitions[0]->routeName);
        self::assertSame('course_show', $definitions[0]->name);
        self::assertSame('Fetch a course.', $definitions[0]->description);
        self::assertTrue($definitions[0]->readOnly);
        self::assertSame('GET', $definitions[0]->method);
        self::assertSame(
            // 'id' is typed 'string' here deliberately: JsonSchemaMapper only ever applies `pattern`
            // when the resulting schema is `{"type": "string"}` — an 'int'-typed argument never
            // carries a pattern, so this is the only type that observably proves ToolCatalog threads
            // the route's `requirements` through to InputSchemaFactory/JsonSchemaMapper correctly.
            ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'pattern' => '\d+']], 'required' => ['id']],
            $definitions[0]->inputSchema,
        );
    }

    #[Test]
    public function picksTheFirstNonOptionsMethod(): void
    {
        $definitions = $this->catalog(
            ['multi' => ['path' => '/api/multi', 'methods' => ['OPTIONS', 'POST'], 'controller' => 'ctrl::multi', 'env' => null, 'requirements' => []]],
            ['multi' => ['name' => 'multi', 'description' => null, 'readOnly' => false, 'excludedReason' => null]],
            ['multi' => []],
        )->list();

        self::assertSame('POST', $definitions[0]->method);
    }

    #[Test]
    public function fallsBackToGetWhenNoMethodIsDeclared(): void
    {
        $definitions = $this->catalog(
            ['any' => ['path' => '/api/any', 'methods' => [], 'controller' => 'ctrl::any', 'env' => null, 'requirements' => []]],
            ['any' => ['name' => 'any', 'description' => null, 'readOnly' => false, 'excludedReason' => null]],
            ['any' => []],
        )->list();

        self::assertSame('GET', $definitions[0]->method);
    }

    /**
     * @param array<string, array{path: string, methods: list<string>, controller: string, env: string|null, requirements: array<string, string>}> $routes
     * @param array<string, array{name: string, description: string|null, readOnly: bool, excludedReason: string|null}>                            $mcpTools
     * @param array<string, list<array{name: string, type: string|null, source: string, nullable: bool, hasDefault: bool, default: mixed}>>        $arguments
     */
    private function catalog(array $routes, array $mcpTools, array $arguments): ToolCatalog
    {
        $registry = new RouteRegistry($routes, new ServiceLocator([]), arguments: $arguments);
        $exposurePolicy = new ExposurePolicy($registry, $mcpTools);

        return new ToolCatalog($registry, $exposurePolicy, new InputSchemaFactory(new JsonSchemaMapper()));
    }
}
