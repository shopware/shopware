<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoaderConfigSpecification::class)]
class LoaderConfigSpecificationTest extends TestCase
{
    #[TestDox('yields empty results from every helper for an empty specification')]
    public function testEmptySpecificationYieldsNothing(): void
    {
        $specification = new LoaderConfigSpecification([]);

        static::assertSame([], $specification->requiredKeys());
        static::assertSame([], $specification->keysOfKind(ConfigKeyKind::Literal));
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
}
