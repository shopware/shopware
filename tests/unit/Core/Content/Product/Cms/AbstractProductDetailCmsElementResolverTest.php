<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\Cms\AbstractProductDetailCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(AbstractProductDetailCmsElementResolver::class)]
class AbstractProductDetailCmsElementResolverTest extends TestCase
{
    public function testCollectReturnsNullIfEntityResolverContextProvided(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product-id-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new EntityResolverContext(
            Generator::createSalesChannelContext(),
            new Request(),
            new SalesChannelProductDefinition(),
            new SalesChannelProductEntity()
        );

        $resolver = new TestProductDetailCmsElementResolver();
        $collection = $resolver->collect($slot, $context);
        static::assertNull($collection);
    }

    public function testCollectReturnsNullIfNoConfigProvided(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());

        $resolver = new TestProductDetailCmsElementResolver();
        $collection = $resolver->collect($slot, $context);
        static::assertNull($collection);
    }

    public function testCollectProductCriteria(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product-id-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());

        $resolver = new TestProductDetailCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $elements = $collection->all();
        static::assertCount(1, $elements);
        static::assertArrayHasKey(SalesChannelProductDefinition::class, $elements);

        $definition = $elements[SalesChannelProductDefinition::class];
        static::assertArrayHasKey('product_slot-1', $definition);

        $criteria = $definition['product_slot-1'];
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame('cms::product-detail-static', $criteria->getTitle());
    }

    public function testGetSlotProductReturnsNullIfNoSearchResultProvided(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $data = new ElementDataCollection();
        $resolver = new TestProductDetailCmsElementResolver();

        static::assertNull($resolver->runGetSlotProduct($slot, $data, 'product-1'));
    }

    public function testGetSlotProduct(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())
            ->method('get')
            ->with('product-1')
            ->willReturn(new SalesChannelProductEntity());

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver = new TestProductDetailCmsElementResolver();
        $product = $resolver->runGetSlotProduct($slot, $data, 'product-1');

        static::assertInstanceOf(SalesChannelProductEntity::class, $product);
    }
}
