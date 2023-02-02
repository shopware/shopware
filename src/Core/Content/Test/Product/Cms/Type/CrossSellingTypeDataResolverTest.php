<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\CrossSellingStruct;
use Shopware\Core\Content\Product\Cms\CrossSellingCmsElementResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CrossSellingTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CrossSellingCmsElementResolver $crossSellingResolver;

    protected function setUp(): void
    {
        $mock = $this->createMock(AbstractProductCrossSellingRoute::class);
        $mock->method('load')->willReturn(
            new ProductCrossSellingRouteResponse(
                new CrossSellingElementCollection()
            )
        );

        $this->crossSellingResolver = new CrossSellingCmsElementResolver($mock);
    }

    public function testType(): void
    {
        static::assertSame('cross-selling', $this->crossSellingResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('cross-selling');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->crossSellingResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('cross-selling');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->crossSellingResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, $criteriaCollection->all());
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('cross-selling');
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->crossSellingResolver->enrich($slot, $resolverContext, $result);

        /** @var CrossSellingStruct|null $crossSellingStruct */
        $crossSellingStruct = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $crossSellingStruct);
        static::assertNull($crossSellingStruct->getCrossSellings());
    }

    public function testEnrichWithConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();
        $result->add('product_id', new EntitySearchResult(
            'product',
            1,
            new ProductCollection(),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('cross-selling');
        $slot->setFieldConfig($fieldConfig);

        $this->crossSellingResolver->enrich($slot, $resolverContext, $result);

        /** @var CrossSellingStruct|null $crossSellingStruct */
        $crossSellingStruct = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $crossSellingStruct);
    }

    public function testCollectWithEmptyProductId(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('cross-selling');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->crossSellingResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }
}
