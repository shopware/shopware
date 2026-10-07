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
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
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
    public function testReturnsNotFoundForMissingProductId(): void
    {
        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new SalesChannelProductCollection()]);
        $loader = new ProductConfiguratorDataLoader(
            new PartialProductConfiguratorLoader(static::createStub(ProductConfiguratorLoader::class)),
            $productRepository,
        );
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        static::assertFalse($loader->load(new LoaderInputs(['productId' => null]), $requirement, Generator::generateSalesChannelContext(), new Request())->hasData());
    }

    public function testDelegatesProductIdToConfiguratorLoader(): void
    {
        $entity = new PartialEntity([
            'id' => 'product-id',
            'parentId' => 'parent-id',
        ]);
        $groups = new PropertyGroupCollection();
        $productConfiguratorLoader = $this->createMock(ProductConfiguratorLoader::class);
        $context = Generator::generateSalesChannelContext();
        $productConfiguratorLoader->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static function (SalesChannelProductEntity $product): bool {
                    return $product->getId() === 'product-id'
                        && $product->getParentId() === 'parent-id'
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

        $result = $loader->load(new LoaderInputs(['productId' => 'product-id']), $requirement, $context, new Request());

        static::assertSame($groups, $result->data);
    }
}
