<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\Cms\AbstractProductDetailCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductDetailCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MockObject&AbstractProductDetailCmsElementResolver $dummyResolver;

    protected function setUp(): void
    {
        $resolverMock = $this->getMockBuilder(AbstractProductDetailCmsElementResolver::class)->onlyMethods(['getType'])->getMockForAbstractClass();
        $resolverMock->method('getType')->willReturn('dummy-type');
        $this->dummyResolver = $resolverMock;
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('dummy-type');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->dummyResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, $criteriaCollection->all());
        /** @var Criteria $criteria */
        $criteria = $criteriaCollection->all()[SalesChannelProductDefinition::class]['product_id'];

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertCount(1, $criteria->getFilters());
        /** @var OrFilter $orFilter */
        static::assertInstanceOf(OrFilter::class, $orFilter = $criteria->getFilters()[0]);
        static::assertCount(2, $queries = $orFilter->getQueries());
        static::assertInstanceOf(EqualsFilter::class, $firstQuery = $queries[0]);
        static::assertEquals('product.parentId', $firstQuery->getField());
        static::assertEquals('product123', $firstQuery->getValue());
        static::assertInstanceOf(EqualsFilter::class, $secondQuery = $queries[1]);
        static::assertEquals('id', $secondQuery->getField());
        static::assertEquals('product123', $secondQuery->getValue());
    }

    public function testCollectWithEmptyStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('dummy-type');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->dummyResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithMappedConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_MAPPED, 'product.name'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('dummy-type');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->dummyResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithEntityResolver(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product-01');
        $entityResolverContext = new EntityResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
            $this->createMock(SalesChannelProductDefinition::class),
            $product
        );

        $fieldConfig = new FieldConfigCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('dummy-type');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->dummyResolver->collect($slot, $entityResolverContext);

        static::assertNull($criteriaCollection);
        /** @var FieldConfig $productConfig */
        static::assertInstanceOf(FieldConfig::class, $productConfig = $fieldConfig->get('product'));
        static::assertEquals(FieldConfig::SOURCE_MAPPED, $productConfig->getSource());
        static::assertEquals($product->getId(), $productConfig->getValue());
    }
}
