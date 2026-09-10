<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductConfiguratorDataLoader::class)]
class ProductConfiguratorDataLoaderTest extends TestCase
{
    public function testReturnsNotFoundForMissingProduct(): void
    {
        $loader = new ProductConfiguratorDataLoader(static::createStub(ProductConfiguratorLoader::class));
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        static::assertFalse($loader->load(new LoaderInputs(['productProperty' => null]), $requirement, Generator::generateSalesChannelContext(), new Request())->hasData());
    }

    public function testDelegatesConfiguredProductToConfiguratorLoader(): void
    {
        $product = (new SalesChannelProductEntity())->assign([
            'id' => 'variant-id',
            'parentId' => 'parent-id',
        ]);
        $groups = new PropertyGroupCollection();
        $configuratorLoader = $this->createMock(ProductConfiguratorLoader::class);
        $context = Generator::generateSalesChannelContext();
        $configuratorLoader->expects($this->once())->method('load')->with($product, $context)->willReturn($groups);

        $loader = new ProductConfiguratorDataLoader($configuratorLoader);
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        $result = $loader->load(new LoaderInputs(['productProperty' => $product]), $requirement, $context, new Request());

        static::assertSame($groups, $result->data);
        static::assertSame([EntityCacheKeyGenerator::buildProductTag('parent-id')], $result->getCacheTags());
    }
}
