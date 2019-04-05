<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SlotDataResolver\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfig;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\Type\ProductSliderTypeDataResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Storefront\StorefrontProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductSliderTypeDataResolverTest extends TestCase
{
    /**
     * @var ProductSliderTypeDataResolver
     */
    private $sliderResolver;

    protected function setUp(): void
    {
        $this->sliderResolver = new ProductSliderTypeDataResolver();
    }

    public function testGetType(): void
    {
        static::assertEquals('product-slider', $this->sliderResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig(new FieldConfigCollection());

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithEmptyStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, ['a', 'b', 'c']));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNotNull($collection);
        static::assertCount(1, $collection->all());
        static::assertEquals(['a', 'b', 'c'], $collection->all()[ProductDefinition::class]['product-slider_id']->getIds());
    }

    public function testCollectWithMappedConfigButWithoutEntityResolverContext(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'category.products'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithMappedConfigButWithInvalidProperty(): void
    {
        $category = new CategoryEntity();
        $category->setUniqueIdentifier('category1');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), CategoryDefinition::class, $category);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'category.foo'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Property foo do not exist in class ' . CategoryEntity::class);

        $this->sliderResolver->collect($slot, $resolverContext);
    }

    public function testCollectWithMappedConfig(): void
    {
        $product1 = new StorefrontProductEntity();
        $product1->setUniqueIdentifier('product1');

        $product2 = new StorefrontProductEntity();
        $product2->setUniqueIdentifier('product2');

        $products = new ProductCollection([$product1, $product2]);

        $category = new CategoryEntity();
        $category->setUniqueIdentifier('category1');
        $category->setProducts($products);

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), CategoryDefinition::class, $category);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'category.products'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithMappedConfigButEmptyManyToManyRelation(): void
    {
        $category = new CategoryEntity();
        $category->setUniqueIdentifier('category1');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), CategoryDefinition::class, $category);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'category.products'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.id', $category->getUniqueIdentifier()));

        static::assertNotNull($collection);
        static::assertEquals($criteria, $collection->all()[ProductDefinition::class]['product-slider-entity-fallback_id']);
    }

    public function testCollectWithMappedConfigButEmptyOneToManyRelation(): void
    {
        $product = new StorefrontProductEntity();
        $product->setUniqueIdentifier('product1');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), ProductDefinition::class, $product);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'product.children'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parent.id', $product->getUniqueIdentifier()));

        static::assertNotNull($collection);
        static::assertEquals($criteria, $collection->all()[ProductDefinition::class]['product-slider-entity-fallback_id']);
    }
}
