<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Product\Cms\ProductBoxCmsElementResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductBoxTypeDataResolverTest extends TestCase
{
    private ProductBoxCmsElementResolver $productBoxResolver;

    private MockObject&SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        $this->systemConfig = $this->createMock(SystemConfigService::class);
        $this->productBoxResolver = new ProductBoxCmsElementResolver($this->systemConfig);
    }

    public function testType(): void
    {
        static::assertSame('product-box', $this->productBoxResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->productBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->productBoxResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, $criteriaCollection->all());
        static::assertSame(['product123'], $criteriaCollection->all()[ProductDefinition::class]['product_id']->getIds());
    }

    public function testCollectWithMappedConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_MAPPED, 'entity.relatedProduct'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->productBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductBoxStruct|null $productBoxStruct */
        $productBoxStruct = $slot->getData();
        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);
        static::assertNull($productBoxStruct->getProductId());
        static::assertNull($productBoxStruct->getProduct());
    }

    /**
     * @dataProvider EnrichWithStaticConfigProvider
     */
    public function testEnrichWithStaticConfig(bool $closeout, bool $hidden, int $availableStock): void
    {
        if ($hidden) {
            $this->systemConfig->method('get')->willReturn(true);
        }

        $salesChannelId = 'f3489c46df62422abdea4aa1bb03511c';

        $product = new SalesChannelProductEntity();
        $product->setId('product123');
        $product->setAvailableStock($availableStock);
        $product->setIsCloseout($closeout);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn($salesChannelId);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $resolverContext = new ResolverContext($salesChannelContext, new Request());
        $result = new ElementDataCollection();
        $result->add('product_id', new EntitySearchResult(
            'product',
            1,
            new ProductCollection([clone $product]),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $slot = $this->enrichProductWithCmsConfig($product);

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);
        /** @var ProductBoxStruct|null $productBoxStruct */
        $productBoxStruct = $slot->getData();
        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);

        /*
         * conditional assertions depending on if a product should be returned or not
         */
        if ($closeout && $hidden && $availableStock === 0) {
            static::assertNull($productBoxStruct->getProductId());
        } else {
            static::assertSame($productBoxStruct->getProductId(), $product->getId());
            static::assertNotSame($product, $productBoxStruct->getProduct());

            $serializedProduct = json_encode($product);
            static::assertNotEquals(\JSON_ERROR_RECURSION, json_last_error());
            static::assertNotFalse($serializedProduct);
        }
    }

    public function testEnrichWithStaticConfigButNoResult(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();
        $result->add('product_id', new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductBoxStruct|null $productBoxStruct */
        $productBoxStruct = $slot->getData();
        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);
        static::assertNull($productBoxStruct->getProductId());
        static::assertNull($productBoxStruct->getProduct());
    }

    public function testEnrichWithMappedConfig(): void
    {
        $parent = new SalesChannelProductEntity();
        $parent->setId('product_parent');

        $product = new SalesChannelProductEntity();
        $product->setId('product123');
        $product->setParent($parent);

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_MAPPED, 'product.parent'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductBoxStruct|null $productBoxStruct */
        $productBoxStruct = $slot->getData();
        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);
        static::assertSame($parent->getId(), $productBoxStruct->getProductId());
    }

    public function testEnrichWithMappedConfigButInvalidProperty(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product123');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_MAPPED, 'product.foo'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Property foo do not exist in class ' . SalesChannelProductEntity::class);

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);
    }

    public function testCollectWithEmptyProductId(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->productBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    /**
     * @return array[] closeout, hidden, availableStock
     *                 This sets if an product can be backordered, if it should be hidden if it can not an is no longer available and the available products
     */
    public static function EnrichWithStaticConfigProvider(): array
    {
        return [
            [false, false, 1],
            [false, true, 1],
            [true, false, 1],
            [true, true, 1],
            [true, true, 0],
        ];
    }

    protected function enrichProductWithCmsConfig(SalesChannelProductEntity $product): CmsSlotEntity
    {
        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, $product->getId()));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setSlot('product-box');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);
        $slot->getFieldConfig();

        $cmsBlock = new CmsBlockEntity();
        $cmsBlock->setUniqueIdentifier('block1');
        $cmsBlock->setSlots(new CmsSlotCollection([$slot]));

        $cmsSection = new CmsSectionEntity();
        $cmsSection->setUniqueIdentifier('section1');
        $cmsSection->setBlocks(new CmsBlockCollection([$cmsBlock]));

        $cmsPage = new CmsPageEntity();
        $cmsPage->setSections(new CmsSectionCollection([$cmsSection]));

        $product->setCmsPage($cmsPage);

        return $slot;
    }
}
