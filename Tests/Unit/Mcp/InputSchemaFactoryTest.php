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
use KonradMichalik\Typo3RoutingMcp\Mcp\InputSchemaFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * InputSchemaFactoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class InputSchemaFactoryTest extends TestCase
{
    #[Test]
    public function mapsARequiredTypedArgument(): void
    {
        $schema = $this->factory()->build(
            [['name' => 'id', 'type' => 'int', 'source' => 'path', 'nullable' => false, 'hasDefault' => false, 'default' => null]],
            [],
        );

        self::assertSame([
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ], $schema);
    }

    #[Test]
    public function omitsARequestSourcedArgument(): void
    {
        $schema = $this->factory()->build(
            [['name' => 'request', 'type' => null, 'source' => 'request', 'nullable' => false, 'hasDefault' => false, 'default' => null]],
            [],
        );

        self::assertSame(['type' => 'object', 'properties' => []], $schema);
    }

    #[Test]
    public function wrapsAVariadicArgumentInAnArraySchema(): void
    {
        $schema = $this->factory()->build(
            [['name' => 'tags', 'type' => 'string', 'source' => 'variadic', 'nullable' => false, 'hasDefault' => false, 'default' => null]],
            [],
        );

        self::assertSame([
            'type' => 'object',
            'properties' => ['tags' => ['type' => 'array', 'items' => ['type' => 'string']]],
            'required' => ['tags'],
        ], $schema);
    }

    #[Test]
    public function omitsAnArgumentWithADefaultOrNullableFromRequired(): void
    {
        $schema = $this->factory()->build(
            [
                ['name' => 'page', 'type' => 'int', 'source' => 'query', 'nullable' => false, 'hasDefault' => true, 'default' => 1],
                ['name' => 'label', 'type' => 'string', 'source' => 'query', 'nullable' => true, 'hasDefault' => false, 'default' => null],
            ],
            [],
        );

        self::assertSame([
            'type' => 'object',
            'properties' => ['page' => ['type' => 'integer'], 'label' => ['type' => 'string']],
        ], $schema);
    }

    #[Test]
    public function appliesARouteRequirementAsAPatternAndNormalisesAnEmptyOneToNull(): void
    {
        $schema = $this->factory()->build(
            [
                ['name' => 'id', 'type' => 'string', 'source' => 'path', 'nullable' => false, 'hasDefault' => false, 'default' => null],
                ['name' => 'slug', 'type' => 'string', 'source' => 'path', 'nullable' => false, 'hasDefault' => false, 'default' => null],
            ],
            ['id' => '\d+', 'slug' => ''],
        );

        self::assertSame(['type' => 'string', 'pattern' => '\d+'], $schema['properties']['id']);
        self::assertSame(['type' => 'string'], $schema['properties']['slug']);
    }

    #[Test]
    public function returnsAnEmptyPropertiesArrayAndNoRequiredKeyForAZeroArgumentRoute(): void
    {
        $schema = $this->factory()->build([], []);

        self::assertSame(['type' => 'object', 'properties' => []], $schema);
        self::assertArrayNotHasKey('required', $schema);
    }

    private function factory(): InputSchemaFactory
    {
        return new InputSchemaFactory(new JsonSchemaMapper());
    }
}
