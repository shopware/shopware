<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledDefinitions;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityMetadata;
use Shopware\Core\Framework\DataAbstractionLayer\MappingMetadata;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompiledDefinitions::class)]
final class CompiledDefinitionsTest extends TestCase
{
    /**
     * @param list<MappingMetadata> $mappings
     */
    #[DataProvider('isEmptyProvider')]
    #[TestDox('isEmpty returns $expected when entity is $entityDesc and mappings is $mappingsDesc')]
    public function testIsEmpty(?EntityMetadata $entity, array $mappings, bool $expected, string $entityDesc, string $mappingsDesc): void
    {
        $compiled = new CompiledDefinitions($entity, $mappings);

        static::assertSame($expected, $compiled->isEmpty());
    }

    /**
     * @return iterable<string, array{entity: EntityMetadata|null, mappings: list<MappingMetadata>, expected: bool, entityDesc: string, mappingsDesc: string}>
     */
    public static function isEmptyProvider(): iterable
    {
        yield 'null entity and empty mappings' => [
            'entity' => null,
            'mappings' => [],
            'expected' => true,
            'entityDesc' => 'null',
            'mappingsDesc' => 'empty',
        ];

        yield 'entity present with empty mappings' => [
            'entity' => new EntityMetadata(
                'test_entity',
                Entity::class,
                EntityCollection::class,
                EntityHydrator::class,
                [],
                null,
                null,
            ),
            'mappings' => [],
            'expected' => false,
            'entityDesc' => 'present',
            'mappingsDesc' => 'empty',
        ];

        yield 'null entity with mappings' => [
            'entity' => null,
            'mappings' => [
                new MappingMetadata('test_mapping', [], 'entity_a', 'entity_b'),
            ],
            'expected' => false,
            'entityDesc' => 'null',
            'mappingsDesc' => 'non-empty',
        ];
    }

    public function testConstructorDefaultMappings(): void
    {
        $compiled = new CompiledDefinitions(null);

        static::assertNull($compiled->entity);
        static::assertSame([], $compiled->mappings);
    }
}
