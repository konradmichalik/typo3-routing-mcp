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

use Countable;
use DateTimeImmutable;
use KonradMichalik\Typo3Routing\OpenApi\JsonSchemaMapper;
use KonradMichalik\Typo3RoutingMcp\Mcp\OutputSchemaFactory;
use KonradMichalik\Typo3RoutingMcp\Tests\Unit\Fixtures\{CourseDto, LecturerDto, PageDto, PublicEntity, PureLevel, SerializedDto, TreeNodeDto, UnionIdDto};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use stdClass;
use Traversable;

/**
 * OutputSchemaFactoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class OutputSchemaFactoryTest extends TestCase
{
    #[Test]
    public function mapsTheSuccessDtoAndAllowsNullOnEveryOptionalProperty(): void
    {
        $schema = $this->factory()->build([
            self::entry(200, CourseDto::class),
            self::entry(404, null),
        ]);

        // Compared as JSON, the form the client validates: `mixed` has to encode as {} rather than [].
        self::assertSame(json_encode([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'lecturer' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'room' => ['type' => ['integer', 'null']],
                    ],
                    'required' => ['name'],
                ],
                'subtitle' => ['type' => ['string', 'null']],
                'level' => ['type' => ['string', 'null'], 'enum' => ['beginner', 'advanced', null]],
                'meta' => (object) [],
            ],
            'required' => ['id', 'title', 'lecturer'],
        ]), json_encode($schema));
    }

    #[Test]
    public function keepsADefaultedNonNullablePropertyNonNullable(): void
    {
        self::assertSame([
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer'],
                'owner' => [
                    'anyOf' => [
                        [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'room' => ['type' => ['integer', 'null']],
                            ],
                            'required' => ['name'],
                        ],
                        ['type' => 'null'],
                    ],
                ],
            ],
        ], $this->factory()->build([self::entry(200, PageDto::class)]));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function dtosWithoutAPreciseSchema(): iterable
    {
        yield 'a union type' => [UnionIdDto::class];
        yield 'an intersection type' => [(new class {
            /** @var Countable&Traversable<int, mixed> */
            public Countable&Traversable $items;
        })::class];
        yield 'an array' => [(new class {
            /** @var array<string, mixed> */
            public array $meta = [];
        })::class];
        yield 'an iterable' => [(new class {
            /** @var iterable<mixed> */
            public iterable $items = [];
        })::class];
        yield 'an object' => [(new class {
            public object $value;
        })::class];
        yield 'a self reference' => [(new class {
            public ?self $parent = null;
        })::class];
        yield 'a pure enum' => [(new class {
            public PureLevel $level = PureLevel::Beginner;
        })::class];
        yield 'a date' => [(new class {
            public DateTimeImmutable $at;
        })::class];
        yield 'a class without public properties' => [(new class {
            public stdClass $value;
        })::class];
        yield 'an Extbase domain object' => [(new class {
            public PublicEntity $entity;
        })::class];
        yield 'a JsonSerializable DTO' => [SerializedDto::class];
        yield 'a nested JsonSerializable DTO' => [(new class {
            public SerializedDto $item;
        })::class];
        yield 'a nested DTO with a union type' => [(new class {
            public UnionIdDto $item;
        })::class];
        yield 'a cycle of DTOs' => [TreeNodeDto::class];
        yield 'no public properties at all' => [(new class {})::class];
    }

    /**
     * @param class-string $class
     */
    #[Test]
    #[DataProvider('dtosWithoutAPreciseSchema')]
    public function returnsNullForADtoTheMapperCannotDescribePrecisely(string $class): void
    {
        self::assertNull($this->factory()->build([self::entry(200, $class)]));
    }

    #[Test]
    public function acceptsTheSameDtoDeclaredForSeveralSuccessStatuses(): void
    {
        $schema = $this->factory()->build([
            self::entry(200, LecturerDto::class),
            self::entry(201, LecturerDto::class),
        ]);

        self::assertSame('object', $schema['type'] ?? null);
    }

    /**
     * @return iterable<string, array{list<array{status: int, schema: class-string|null, collection: bool, description: string|null}>}>
     */
    public static function returnsWithoutAnObjectSchema(): iterable
    {
        yield 'no #[Returns]' => [[]];
        yield 'only an error response' => [[self::entry(404, LecturerDto::class)]];
        yield 'a success response without body' => [[self::entry(204, null)]];
        yield 'a collection' => [[self::entry(200, LecturerDto::class, collection: true)]];
        yield 'a DTO next to an empty success response' => [[self::entry(200, LecturerDto::class), self::entry(204, null)]];
        yield 'two different DTOs' => [[self::entry(200, LecturerDto::class), self::entry(201, CourseDto::class)]];
    }

    /**
     * @param list<array{status: int, schema: class-string|null, collection: bool, description: string|null}> $returns
     */
    #[Test]
    #[DataProvider('returnsWithoutAnObjectSchema')]
    public function returnsNullUnlessEverySuccessResponseIsTheSameSingleDto(array $returns): void
    {
        self::assertNull($this->factory()->build($returns));
    }

    /**
     * @param class-string|null $schema
     *
     * @return array{status: int, schema: class-string|null, collection: bool, description: string|null}
     */
    private static function entry(int $status, ?string $schema, bool $collection = false): array
    {
        return ['status' => $status, 'schema' => $schema, 'collection' => $collection, 'description' => null];
    }

    private function factory(): OutputSchemaFactory
    {
        return new OutputSchemaFactory(new JsonSchemaMapper());
    }
}
