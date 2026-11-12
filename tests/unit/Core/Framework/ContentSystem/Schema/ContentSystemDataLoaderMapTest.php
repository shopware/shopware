<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\Fixture\LoaderConfigSpecificationFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemDataLoaderMap::class)]
class ContentSystemDataLoaderMapTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('scansSubtypesProvider')]
    #[TestDox('resolves source identifiers for $_dataName')]
    public function testGetSourcesForIsSubtypeAware(string $className, array $expected): void
    {
        static::assertSame($expected, $this->map()->getSourcesFor($className));
    }

    #[TestDox('capabilityFor returns the matching capability for a producible class')]
    public function testCapabilityForReturnsMatch(): void
    {
        $capability = $this->map()->capabilityFor('entity', ProductEntity::class);

        static::assertInstanceOf(LoaderTypeCapability::class, $capability);
        static::assertSame(SalesChannelProductEntity::class, $capability->producedType);
        static::assertSame(['entity' => 'product'], $capability->configTemplate);
    }

    #[TestDox('configSpecificationFor returns the registered specification for a known source')]
    public function testConfigSpecificationForReturnsRegisteredSpecification(): void
    {
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
        ]);

        $map = new ContentSystemDataLoaderMap(['entity' => []], ['entity' => $specification]);

        static::assertSame($specification, $map->configSpecificationFor('entity'));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('derivesResidualConfigKeysProvider')]
    #[TestDox('residualConfigKeysFor derives $_dataName')]
    public function testResidualConfigKeysFor(
        LoaderConfigSpecification $specification,
        LoaderTypeCapability $capability,
        array $expected,
    ): void {
        $map = new ContentSystemDataLoaderMap(
            ['source' => [$capability]],
            ['source' => $specification],
        );

        static::assertSame($expected, $map->residualConfigKeysFor('source', $capability));
    }

    #[TestDox('capabilityFor returns null when the source cannot produce the class')]
    public function testCapabilityForReturnsNullWhenSourceCannotProduce(): void
    {
        static::assertNull($this->map()->capabilityFor('entity', MediaEntity::class));
    }

    #[TestDox('capabilityFor returns null for an unknown source')]
    public function testCapabilityForReturnsNullForUnknownSource(): void
    {
        static::assertNull($this->map()->capabilityFor('unknown', ProductEntity::class));
    }

    #[TestDox('configSpecificationFor throws data-loader-not-registered for an unknown source instead of returning an empty specification')]
    public function testConfigSpecificationForThrowsForUnknownSource(): void
    {
        $map = new ContentSystemDataLoaderMap([], []);

        try {
            $map->configSpecificationFor('ghost');
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::DATA_LOADER_NOT_REGISTERED, $exception->getErrorCode());
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function scansSubtypesProvider(): iterable
    {
        yield 'a base property via a sales-channel subclass producer' => [ProductEntity::class, ['entity']];
        yield 'a sales-channel property via an equal sales-channel producer' => [SalesChannelProductEntity::class, ['entity']];
        yield 'a base property via an equal base producer (no sales-channel variant)' => [MediaEntity::class, ['media']];
        yield 'no source for an unrelated type' => [CategoryEntity::class, []];
    }

    /**
     * @return iterable<string, array{LoaderConfigSpecification, LoaderTypeCapability, list<string>}>
     */
    public static function derivesResidualConfigKeysProvider(): iterable
    {
        yield 'a required key the template does not fill as the residual' => [
            LoaderConfigSpecificationFixture::entityProperty(),
            new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product']),
            ['property'],
        ];

        yield 'multiple residual keys in specification declaration order (order-preserving)' => [
            new LoaderConfigSpecification([
                new ConfigKeySpecification('zeta', ConfigKeyKind::Literal, 'string', required: true),
                new ConfigKeySpecification('alpha', ConfigKeyKind::Literal, 'string', required: true),
            ]),
            new LoaderTypeCapability(MediaEntity::class),
            ['zeta', 'alpha'],
        ];

        yield 'an empty residual when the template already fills the required key' => [
            new LoaderConfigSpecification([
                new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            ]),
            new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product']),
            [],
        ];

        yield 'an empty residual from an empty specification' => [
            new LoaderConfigSpecification([]),
            new LoaderTypeCapability(MediaEntity::class),
            [],
        ];

        yield 'an empty residual for a config-less source carrying a non-empty template' => [
            new LoaderConfigSpecification([]),
            new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product']),
            [],
        ];
    }

    private function map(): ContentSystemDataLoaderMap
    {
        return new ContentSystemDataLoaderMap(
            [
                'entity' => [new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product'])],
                'media' => [new LoaderTypeCapability(MediaEntity::class)],
            ],
            [
                'entity' => LoaderConfigSpecificationFixture::entityProperty(),
                'media' => new LoaderConfigSpecification([]),
            ],
        );
    }
}
