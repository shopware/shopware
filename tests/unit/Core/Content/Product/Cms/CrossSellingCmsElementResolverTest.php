<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\CrossSellingStruct;
use Shopware\Core\Content\Product\Cms\CrossSellingCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElement;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CrossSellingCmsElementResolver::class)]
class CrossSellingCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $route = $this->createMock(AbstractProductCrossSellingRoute::class);
        $resolver = new CrossSellingCmsElementResolver($route);
        static::assertSame('cross-selling', $resolver->getType());
    }

    public function testEnrichStaticSlotWithCrossSelling(): void
    {
        $productId = 'product-1';

        $response = new ProductCrossSellingRouteResponse(new CrossSellingElementCollection([
            (new CrossSellingElement())->assign(['total' => 1]),
        ]));

        $route = $this->createMock(AbstractProductCrossSellingRoute::class);
        $route->method('load')->with($productId)->willReturn($response);

        $resolver = new CrossSellingCmsElementResolver($route);
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId),
        ]);

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
        static::assertInstanceOf(CrossSellingStruct::class, $data);

        $collection = $data->getCrossSellings();
        static::assertInstanceOf(CrossSellingElementCollection::class, $collection);

        $crossSelling = $collection->first();
        static::assertInstanceOf(CrossSellingElement::class, $crossSelling);
        static::assertSame(1, $crossSelling->getTotal());
    }

    public function testEnrichSetsEmptyCrossSellingWithoutConfig(): void
    {
        $route = $this->createMock(AbstractProductCrossSellingRoute::class);
        $resolver = new CrossSellingCmsElementResolver($route);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $data);
        static::assertNull($data->getCrossSellings());
    }
}
