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

namespace KonradMichalik\Typo3RoutingMcp\Mcp;

use BackedEnum;
use JsonSerializable;
use KonradMichalik\Typo3Routing\OpenApi\JsonSchemaMapper;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function assert;
use function class_exists;
use function count;
use function enum_exists;
use function in_array;
use function is_a;
use function is_array;
use function is_string;

/**
 * OutputSchemaFactory.
 *
 * mcp/sdk rejects structuredContent that fails the outputSchema, so only a single success DTO whose
 * properties all map precisely gets one. Collections are skipped, older MCP revisions need an object root.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class OutputSchemaFactory
{
    private const PRECISE_BUILTIN_TYPES = ['int', 'float', 'bool', 'string', 'mixed'];

    public function __construct(
        private JsonSchemaMapper $schemas,
    ) {}

    /**
     * @param list<array{status: int, schema: class-string|null, collection: bool, description: string|null}> $returns
     *
     * @return array<string, mixed>|null
     */
    public function build(array $returns): ?array
    {
        $success = array_filter($returns, static fn (array $return): bool => $return['status'] >= 200 && $return['status'] < 300);
        $classes = array_values(array_unique(array_map(
            static fn (array $return): ?string => $return['collection'] ? null : $return['schema'],
            $success,
        )));

        if (1 !== count($classes) || null === $classes[0] || !$this->isPreciselyMappable($classes[0], [])) {
            return null;
        }

        return $this->withNullability($this->schemas->objectSchemaForClass($classes[0]), $classes[0]);
    }

    /**
     * @param class-string       $class
     * @param list<class-string> $visiting
     */
    private function isPreciselyMappable(string $class, array $visiting): bool
    {
        if (in_array($class, $visiting, true) || is_a($class, JsonSerializable::class, true)) {
            return false;
        }

        $properties = $this->publicProperties($class);
        foreach ($properties as $property) {
            if (!$this->isPreciselyMappableType($property, [...$visiting, $class])) {
                return false;
            }
        }

        return [] !== $properties;
    }

    /**
     * @param list<class-string> $visiting
     */
    private function isPreciselyMappableType(ReflectionProperty $property, array $visiting): bool
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        $name = $type->getName();
        if ($type->isBuiltin()) {
            return in_array($name, self::PRECISE_BUILTIN_TYPES, true);
        }

        if (enum_exists($name)) {
            return is_a($name, BackedEnum::class, true);
        }

        return class_exists($name)
            && !is_a($name, DomainObjectInterface::class, true)
            && $this->isPreciselyMappable($name, $visiting);
    }

    /**
     * JsonSchemaMapper keeps a nullable property's plain type (typo3-routing issue #160).
     * Drop this once a release with that fix is required.
     *
     * @param array<string, mixed> $schema
     * @param class-string         $class
     *
     * @return array<string, mixed>
     */
    private function withNullability(array $schema, string $class): array
    {
        /** @var array<string, array<string, mixed>> $properties */
        $properties = $schema['properties'];
        foreach ($this->publicProperties($class) as $property) {
            $type = $property->getType();
            assert($type instanceof ReflectionNamedType);

            $name = $property->getName();
            $typeName = $type->getName();
            if (!$type->isBuiltin() && !enum_exists($typeName) && class_exists($typeName)) {
                $properties[$name] = $this->withNullability($properties[$name], $typeName);
            }

            if ($type->allowsNull()) {
                $properties[$name] = $this->nullable($properties[$name]);
            }
        }

        return [...$schema, 'properties' => $properties];
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function nullable(array $schema): array
    {
        if (!is_string($schema['type'] ?? null)) {
            return $schema;
        }

        $nullable = [...$schema, 'type' => [$schema['type'], 'null']];
        if (is_array($schema['enum'] ?? null)) {
            $nullable['enum'] = [...$schema['enum'], null];
        }

        return $nullable;
    }

    /**
     * Mirrors which properties JsonSchemaMapper and json_encode() see.
     *
     * @param class-string $class
     *
     * @return list<ReflectionProperty>
     */
    private function publicProperties(string $class): array
    {
        $properties = (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC);

        return array_values(array_filter($properties, static fn (ReflectionProperty $property): bool => !$property->isStatic()));
    }
}
