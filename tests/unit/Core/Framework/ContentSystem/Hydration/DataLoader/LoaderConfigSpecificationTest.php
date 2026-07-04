<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;

/**
 * @internal
 */
#[CoversClass(LoaderConfigSpecification::class)]
class LoaderConfigSpecificationTest extends TestCase
{
    #[TestDox('yields empty results from every helper for an empty specification')]
    public function testEmptySpecificationYieldsNothing(): void
    {
        $specification = new LoaderConfigSpecification([]);

        static::assertSame([], $specification->requiredKeys());
        static::assertSame([], $specification->keysOfKind(ConfigKeyKind::Literal));
        static::assertNull($specification->get('entity'));
    }

    #[TestDox('requiredKeys returns only the names of required keys, in declaration order')]
    public function testRequiredKeysReturnsOnlyRequiredKeyNames(): void
    {
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', true),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', false, true, []),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', true),
        ]);

        static::assertSame(['entity', 'property'], $specification->requiredKeys());
    }

    #[TestDox('keysOfKind returns the keys of one kind, preserving declaration order')]
    public function testKeysOfKindFiltersByKindPreservingOrder(): void
    {
        $entity = new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', true);
        $rootId = new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', false, true, null);
        $associations = new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', false, true, []);
        $property = new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', true);

        $specification = new LoaderConfigSpecification([$entity, $rootId, $associations, $property]);

        static::assertSame([$rootId, $associations], $specification->keysOfKind(ConfigKeyKind::Literal));
    }

    /**
     * @param list<ConfigKeySpecification> $keys
     */
    #[DataProvider('looksUpByNameProvider')]
    #[TestDox('looks up a key by name: $_dataName')]
    public function testGetLooksUpKeyByName(array $keys, string $name, ?ConfigKeySpecification $expected): void
    {
        $specification = new LoaderConfigSpecification($keys);

        static::assertSame($expected, $specification->get($name));
    }

    /**
     * @return iterable<string, array{list<ConfigKeySpecification>, string, ConfigKeySpecification|null}>
     */
    public static function looksUpByNameProvider(): iterable
    {
        $entity = new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', true);
        $property = new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', true);

        yield 'returns the exact instance for a known name' => [[$entity, $property], 'property', $property];
        yield 'returns null for an unknown name' => [[$entity], 'property', null];
    }
}
