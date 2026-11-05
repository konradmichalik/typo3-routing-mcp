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
use KonradMichalik\Typo3RoutingMcp\Mcp\{ExposurePolicy, InputSchemaFactory, OutputSchemaFactory, ToolCatalog};
use KonradMichalik\Typo3RoutingMcp\Tests\Unit\Fixtures\LecturerDto;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * ToolCatalogTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 *
 * @phpstan-import-type ToolEntry from ExposurePolicy
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
                'course_show' => ['name' => 'course_show', 'description' => 'Fetch a course.', 'readOnly' => true, 'destructive' => null, 'idempotent' => null, 'excludedReason' => null],
                'secure_route' => ['name' => 'secure_route', 'description' => null, 'readOnly' => false, 'destructive' => null, 'idempotent' => null, 'excludedReason' => 'Guarded.'],
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
    public function wiresTheOutputSchemaFromTheRoutesReturns(): void
    {
        $definitions = $this->catalog(
            [
                'with_returns' => ['path' => '/api/lecturer', 'methods' => ['GET'], 'controller' => 'ctrl::lecturer', 'env' => null, 'requirements' => []],
                'without_returns' => ['path' => '/api/plain', 'methods' => ['GET'], 'controller' => 'ctrl::plain', 'env' => null, 'requirements' => []],
            ],
            [
                'with_returns' => ['name' => 'with_returns', 'description' => null, 'readOnly' => true, 'destructive' => null, 'idempotent' => null, 'excludedReason' => null],
                'without_returns' => ['name' => 'without_returns', 'description' => null, 'readOnly' => true, 'destructive' => null, 'idempotent' => null, 'excludedReason' => null],
            ],
            ['with_returns' => [], 'without_returns' => []],
            ['with_returns' => [['status' => 200, 'schema' => LecturerDto::class, 'collection' => false, 'description' => null]]],
        )->list();

        self::assertSame('object', $definitions[0]->outputSchema['type'] ?? null);
        self::assertNull($definitions[1]->outputSchema);
    }

    #[Test]
    public function picksTheFirstNonOptionsMethod(): void
    {
        $definitions = $this->catalog(
            ['multi' => ['path' => '/api/multi', 'methods' => ['OPTIONS', 'POST'], 'controller' => 'ctrl::multi', 'env' => null, 'requirements' => []]],
            ['multi' => ['name' => 'multi', 'description' => null, 'readOnly' => false, 'destructive' => null, 'idempotent' => null, 'excludedReason' => null]],
            ['multi' => []],
        )->list();

        self::assertSame('POST', $definitions[0]->method);
    }

    #[Test]
    public function fallsBackToGetWhenNoMethodIsDeclared(): void
    {
        $definitions = $this->catalog(
            ['any' => ['path' => '/api/any', 'methods' => [], 'controller' => 'ctrl::any', 'env' => null, 'requirements' => []]],
            ['any' => ['name' => 'any', 'description' => null, 'readOnly' => false, 'destructive' => null, 'idempotent' => null, 'excludedReason' => null]],
            ['any' => []],
        )->list();

        self::assertSame('GET', $definitions[0]->method);
    }

    /**
     * @return iterable<string, array{string, bool, bool|null, bool|null, bool|null, bool|null}>
     */
    public static function hintCases(): iterable
    {
        yield 'GET write' => ['GET', false, null, null, false, true];
        yield 'POST' => ['POST', false, null, null, false, false];
        yield 'PUT' => ['PUT', false, null, null, false, true];
        yield 'DELETE' => ['DELETE', false, null, null, true, true];
        yield 'read-only DELETE carries neither' => ['DELETE', true, null, null, null, null];
        yield 'read-only ignores overrides' => ['POST', true, true, true, null, null];
        yield 'POST overridden' => ['POST', false, true, true, true, true];
        yield 'DELETE overridden' => ['DELETE', false, false, false, false, false];
    }

    #[Test]
    #[DataProvider('hintCases')]
    public function derivesDestructiveAndIdempotentHintsFromTheMethodUnlessOverridden(
        string $method,
        bool $readOnly,
        ?bool $destructiveOverride,
        ?bool $idempotentOverride,
        ?bool $expectedDestructive,
        ?bool $expectedIdempotent,
    ): void {
        $definitions = $this->catalog(
            ['tool' => ['path' => '/api/tool', 'methods' => [$method], 'controller' => 'ctrl::tool', 'env' => null, 'requirements' => []]],
            ['tool' => ['name' => 'tool', 'description' => null, 'readOnly' => $readOnly, 'destructive' => $destructiveOverride, 'idempotent' => $idempotentOverride, 'excludedReason' => null]],
            ['tool' => []],
        )->list();

        self::assertSame($expectedDestructive, $definitions[0]->destructive);
        self::assertSame($expectedIdempotent, $definitions[0]->idempotent);
    }

    /**
     * @param array<string, array{path: string, methods: list<string>, controller: string, env: string|null, requirements: array<string, string>}> $routes
     * @param array<string, ToolEntry>                                                                                                             $mcpTools
     * @param array<string, list<array{name: string, type: string|null, source: string, nullable: bool, hasDefault: bool, default: mixed}>>        $arguments
     * @param array<string, list<array{status: int, schema: class-string|null, collection: bool, description: string|null}>>                       $returns
     */
    private function catalog(array $routes, array $mcpTools, array $arguments, array $returns = []): ToolCatalog
    {
        $registry = new RouteRegistry($routes, new ServiceLocator([]), arguments: $arguments, returns: $returns);
        $exposurePolicy = new ExposurePolicy($registry, $mcpTools);
        $schemas = new JsonSchemaMapper();

        return new ToolCatalog($registry, $exposurePolicy, new InputSchemaFactory($schemas), new OutputSchemaFactory($schemas));
    }
}
