<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\BuyBoxStruct;
use Shopware\Core\Content\Product\Cms\BuyBoxCmsElementResolver;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(BuyBoxCmsElementResolver::class)]
class BuyBoxCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = new BuyBoxCmsElementResolver(
            $this->createMock(ProductConfiguratorLoader::class),
            new StaticEntityRepository([])
        );

        static::assertSame('buy-box', $resolver->getType());
    }

    public function testEnrichBuyBox(): void
    {
        $configurationLoader = $this->createMock(ProductConfiguratorLoader::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('aggregate')->willReturn(new AggregationResultCollection());

        $resolver = new BuyBoxCmsElementResolver($configurationLoader, $repository);

        $productId = 'product-1';
        $config = new FieldConfigCollection([new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId)]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());

        $result = $this->createMock(EntitySearchResult::class);

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $result->method('get')
            ->with($productId)
            ->willReturn($product);

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $data);

        $product = $data->getProduct();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('product-1', $product->getId());
    }

    public function testEnrichSetsEmptyBuyBoxWithoutConfig(): void
    {
        $configurationLoader = $this->createMock(ProductConfiguratorLoader::class);
        $resolver = new BuyBoxCmsElementResolver($configurationLoader, new StaticEntityRepository([]));

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $data);
        static::assertNull($data->getProduct());
    }
}
