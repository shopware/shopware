<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductDescriptionReviewsTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ProductDescriptionReviewsCmsElementResolver $productDescriptionReviewResolver;

    protected function setUp(): void
    {
        $productReviewRouteMock = $this->createMock(AbstractProductReviewRoute::class);
        $productReviewRouteMock->method('load')->willReturn(
            new ProductReviewRouteResponse(
                new EntitySearchResult('product', 0, new EntityCollection(), null, new Criteria(), Context::createDefaultContext())
            )
        );

        $this->productDescriptionReviewResolver = new ProductDescriptionReviewsCmsElementResolver(
            $productReviewRouteMock
        );
    }

    public function testType(): void
    {
        static::assertSame('product-description-reviews', $this->productDescriptionReviewResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-description-reviews');

        $collection = $this->productDescriptionReviewResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutContext(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-description-reviews');

        $this->productDescriptionReviewResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductDescriptionReviewsStruct|null $productDescriptionReviewStruct */
        $productDescriptionReviewStruct = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $productDescriptionReviewStruct);
        static::assertNull($productDescriptionReviewStruct->getProduct());
    }
}
