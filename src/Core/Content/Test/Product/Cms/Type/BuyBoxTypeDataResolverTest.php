<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\BuyBoxStruct;
use Shopware\Core\Content\Product\Cms\BuyBoxCmsElementResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class BuyBoxTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var BuyBoxCmsElementResolver
     */
    private $buyBoxResolver;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10078', $this);

        $mockProductDetailRoute = $this->createMock(ProductDetailRoute::class);
        $saleChannelProductEntity = new SalesChannelProductEntity();
        $saleChannelProductEntity->setId('product123');
        $mockProductDetailRoute->method('load')->willReturn(
            new ProductDetailRouteResponse(
                $saleChannelProductEntity,
                new PropertyGroupCollection()
            )
        );

        $mockConfiguratorLoader = $this->createMock(ProductConfiguratorLoader::class);
        $mockConfiguratorLoader->method('load')->willReturn(
            new PropertyGroupCollection()
        );

        $this->buyBoxResolver = new BuyBoxCmsElementResolver($mockProductDetailRoute, $mockConfiguratorLoader);
    }

    public function testGetType(): void
    {
        static::assertSame('buy-box', $this->buyBoxResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->buyBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->buyBoxResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, $criteriaCollection->all());
        static::assertSame(['product123'], $criteriaCollection->all()[ProductDefinition::class]['product_id']->getIds());
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->buyBoxResolver->enrich($slot, $resolverContext, $result);

        /** @var BuyBoxStruct|null $buyBoxStruct */
        $buyBoxStruct = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $buyBoxStruct);
        static::assertNull($buyBoxStruct->getProductId());
        static::assertNull($buyBoxStruct->getProduct());
    }

    public function testEnrichWithStaticConfig(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product123');

        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();
        $result->add('product_id', new EntitySearchResult(
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $this->buyBoxResolver->enrich($slot, $resolverContext, $result);

        /** @var BuyBoxStruct|null $buyBoxStruct */
        $buyBoxStruct = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $buyBoxStruct);
        static::assertSame($product->getId(), $buyBoxStruct->getProductId());
    }

    public function testCollectWithEmptyProductId(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->buyBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }
}
