<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductConfiguratorDataLoader::class)]
class ProductConfiguratorDataLoaderTest extends TestCase
{
    public function testReturnsNotFoundForWrongConfig(): void
    {
        $loader = new ProductConfiguratorDataLoader(static::createStub(ProductConfiguratorLoader::class));
        $element = ContentElementBuilder::create('variant-selection')->build();
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        static::assertFalse($loader->load($element, $requirement, Generator::generateSalesChannelContext(), new Request())->hasData());
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
        $element = ContentElementBuilder::create('variant-selection')->withProperty('product', $product)->build();
        $requirement = new DataRequirement('configuratorSettings', ProductConfiguratorDataLoader::SOURCE, new ProductConfiguratorLoaderConfig());

        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertSame($groups, $result->data);
        static::assertSame([EntityCacheKeyGenerator::buildProductTag('parent-id')], $result->getCacheTags());
    }
}
