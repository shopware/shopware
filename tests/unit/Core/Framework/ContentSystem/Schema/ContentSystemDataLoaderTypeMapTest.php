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
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeMap;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderTypeMap::class)]
class ContentSystemDataLoaderTypeMapTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('subtypeScanProvider')]
    #[TestDox('getSourcesFor resolves $_dataName')]
    public function testGetSourcesForIsSubtypeAware(string $className, array $expected): void
    {
        static::assertSame($expected, $this->map()->getSourcesFor($className));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function subtypeScanProvider(): iterable
    {
        yield 'a base property via a sales-channel subclass producer' => [ProductEntity::class, ['entity']];
        yield 'a sales-channel property via an equal sales-channel producer' => [SalesChannelProductEntity::class, ['entity']];
        yield 'a base property via an equal base producer (no sales-channel variant)' => [MediaEntity::class, ['media']];
        yield 'no source for an unrelated type' => [CategoryEntity::class, []];
    }

    #[TestDox('capabilityFor returns the matching capability for a producible class')]
    public function testCapabilityForReturnsMatch(): void
    {
        $capability = $this->map()->capabilityFor('entity', ProductEntity::class);

        static::assertInstanceOf(LoaderTypeCapability::class, $capability);
        static::assertSame(SalesChannelProductEntity::class, $capability->producedType);
        static::assertSame(['entity' => 'product'], $capability->configTemplate);
        static::assertSame(['property'], $capability->requiredConfigKeys);
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

    private function map(): ContentSystemDataLoaderTypeMap
    {
        return new ContentSystemDataLoaderTypeMap([
            'entity' => [new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product'], ['property'])],
            'media' => [new LoaderTypeCapability(MediaEntity::class)],
        ]);
    }
}
