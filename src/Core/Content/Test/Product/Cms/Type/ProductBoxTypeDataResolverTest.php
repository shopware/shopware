<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfig;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Product\Cms\Type\ProductBoxTypeDataResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductBoxTypeDataResolverTest extends TestCase
{
    /**
     * @var ProductBoxTypeDataResolver
     */
    private $productBoxResolver;

    protected function setUp(): void
    {
        $this->productBoxResolver = new ProductBoxTypeDataResolver();
    }

    public function testType(): void
    {
        static::assertEquals('product-box', $this->productBoxResolver->getType());
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
        static::assertEquals(['product123'], $criteriaCollection->all()[ProductDefinition::class]['product_id']->getIds());
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
        $result = new SlotDataResolveResult();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);

        static::assertInstanceOf(ProductBoxStruct::class, $slot->getData());
        static::assertNull($slot->getData()->getProductId());
        static::assertNull($slot->getData()->getProduct());
    }

    public function testEnrichWithStaticConfig(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product123');

        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new SlotDataResolveResult();
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
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);

        static::assertInstanceOf(ProductBoxStruct::class, $slot->getData());
        static::assertEquals($product->getId(), $slot->getData()->getProductId());
        static::assertSame($product, $slot->getData()->getProduct());
    }

    public function testEnrichWithStaticConfigButNoResult(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new SlotDataResolveResult();
        $result->add('product_id', new EntitySearchResult(
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

        static::assertInstanceOf(ProductBoxStruct::class, $slot->getData());
        static::assertNull($slot->getData()->getProductId());
        static::assertNull($slot->getData()->getProduct());
    }

    public function testEnrichWithMappedConfig(): void
    {
        $parent = new SalesChannelProductEntity();
        $parent->setId('product_parent');

        $product = new SalesChannelProductEntity();
        $product->setId('product123');
        $product->setParent($parent);

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), ProductDefinition::class, $product);
        $result = new SlotDataResolveResult();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_MAPPED, 'product.parent'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);

        static::assertInstanceOf(ProductBoxStruct::class, $slot->getData());
        static::assertEquals($parent->getId(), $slot->getData()->getProductId());
        static::assertSame($parent, $slot->getData()->getProduct());
    }

    public function testEnrichWithMappedConfigButInvalidProperty(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product123');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), ProductDefinition::class, $product);
        $result = new SlotDataResolveResult();

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
}
