<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Suggest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ResolvedCriteriaProductSuggestRoute;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductSuggestRoute::class)]
class ProductSuggestRouteTest extends TestCase
{
    /**
     * @var ProductListingLoader&MockObject
     */
    private ProductListingLoader $listingLoader;

    protected function setUp(): void
    {
        $this->listingLoader = $this->createMock(ProductListingLoader::class);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->getProductSuggestRoute()->getDecorated();
    }

    public function testLoadThrowsExceptionForMissingSearchParameter(): void
    {
        static::expectException(RoutingException::class);

        $route = new ResolvedCriteriaProductSuggestRoute(
            $this->createMock(ProductSearchBuilderInterface::class),
            new EventDispatcher(),
            $this->createMock(AbstractProductSuggestRoute::class),
            new CompositeListingProcessor([])
        );

        $route->load(
            new Request(),
            $this->createMock(SalesChannelContext::class),
            new Criteria()
        );
    }

    public function testLoadSuccessfully(): void
    {
        $request = new Request();
        $request->query->set('search', 'test');

        $criteria = new Criteria();

        $this->listingLoader->expects(static::once())
            ->method('load')
            ->willReturn(new ProductListingResult(
                ProductDefinition::ENTITY_NAME,
                1,
                new ProductCollection([]),
                null,
                $criteria,
                Context::createDefaultContext()
            ));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $this->getProductSuggestRoute()->load(
            $request,
            $salesChannelContext,
            $criteria
        );
    }

    private function getProductSuggestRoute(): ProductSuggestRoute
    {
        return new ProductSuggestRoute(
            $this->listingLoader
        );
    }
}
