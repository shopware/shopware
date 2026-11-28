<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\Detail\PartialProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductConfiguratorDataLoader::class)]
class ProductConfiguratorDataLoaderTest extends TestCase
{
    public function testReturnsNoInputForMissingProductId(): void
    {
        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new SalesChannelProductCollection()]);
        $loader = new ProductConfiguratorDataLoader(
            new PartialProductConfiguratorLoader(static::createStub(ProductConfiguratorLoader::class)),
            $productRepository,
        );
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        $result = $loader->load(new LoaderInputs(['productId' => null]), $requirement, Generator::generateSalesChannelContext(), new Request());

        $this->assertNoInputResult($result);
    }

    public function testReturnsNoInputForInvalidProductId(): void
    {
        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([
            static fn () => static::fail('The product search must not run for an invalid product id'),
        ]);
        $loader = new ProductConfiguratorDataLoader(
            new PartialProductConfiguratorLoader(static::createStub(ProductConfiguratorLoader::class)),
            $productRepository,
        );
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        $result = $loader->load(new LoaderInputs(['productId' => '{{productId}}']), $requirement, Generator::generateSalesChannelContext(), new Request());

        $this->assertNoInputResult($result);
    }

    public function testTagsTheRequestedProductWhenProductSearchThrows(): void
    {
        $productId = Uuid::randomHex();

        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([
            static fn () => throw new InvalidUuidException('product-id'),
        ]);
        $loader = new ProductConfiguratorDataLoader(
            new PartialProductConfiguratorLoader(static::createStub(ProductConfiguratorLoader::class)),
            $productRepository,
        );
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        $result = $loader->load(new LoaderInputs(['productId' => $productId]), $requirement, Generator::generateSalesChannelContext(), new Request());

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([EntityCacheKeyGenerator::buildProductTag($productId)], $result->getCacheTags());
    }

    public function testTagsTheRequestedProductWhenProductSearchFindsNothing(): void
    {
        $productId = Uuid::randomHex();

        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new SalesChannelProductCollection()]);
        $loader = new ProductConfiguratorDataLoader(
            new PartialProductConfiguratorLoader(static::createStub(ProductConfiguratorLoader::class)),
            $productRepository,
        );
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        $result = $loader->load(new LoaderInputs(['productId' => $productId]), $requirement, Generator::generateSalesChannelContext(), new Request());

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([EntityCacheKeyGenerator::buildProductTag($productId)], $result->getCacheTags());
    }

    public function testTagsTheParentProductWhenConfiguratorLoaderThrows(): void
    {
        $productId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $productConfiguratorLoader = static::createStub(ProductConfiguratorLoader::class);
        $productConfiguratorLoader->method('load')->willThrowException(new InvalidUuidException('option-id'));

        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([[new PartialEntity(['id' => $productId, 'parentId' => $parentId])]]);
        $loader = new ProductConfiguratorDataLoader(
            new PartialProductConfiguratorLoader($productConfiguratorLoader),
            $productRepository,
        );
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        $result = $loader->load(new LoaderInputs(['productId' => $productId]), $requirement, Generator::generateSalesChannelContext(), new Request());

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([EntityCacheKeyGenerator::buildProductTag($parentId)], $result->getCacheTags());
    }

    public function testDelegatesProductIdToConfiguratorLoaderAndTagsTheParentProduct(): void
    {
        $productId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $entity = new PartialEntity([
            'id' => $productId,
            'parentId' => $parentId,
        ]);
        $groups = new PropertyGroupCollection();
        $productConfiguratorLoader = $this->createMock(ProductConfiguratorLoader::class);
        $context = Generator::generateSalesChannelContext();
        $productConfiguratorLoader->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static function (SalesChannelProductEntity $product) use ($productId, $parentId): bool {
                    return $product->getId() === $productId
                        && $product->getParentId() === $parentId
                        && $product->getOptionIds() === null
                        && $product->getVariantListingConfig() === null;
                }),
                $context,
            )
            ->willReturn($groups);

        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([[$entity]]);
        $loader = new ProductConfiguratorDataLoader(
            new PartialProductConfiguratorLoader($productConfiguratorLoader),
            $productRepository,
        );
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        $result = $loader->load(new LoaderInputs(['productId' => $productId]), $requirement, $context, new Request());

        static::assertSame($groups, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([EntityCacheKeyGenerator::buildProductTag($parentId)], $result->getCacheTags());
    }

    private function assertNoInputResult(ContentDataLoaderResult $result): void
    {
        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }
}
