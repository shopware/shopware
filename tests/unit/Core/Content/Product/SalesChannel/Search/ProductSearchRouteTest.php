<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductSearchRoute::class)]
class ProductSearchRouteTest extends TestCase
{
    /**
     * @var ProductListingLoader&MockObject
     */
    private ProductListingLoader $listingLoader;

    /**
     * @var ProductSearchBuilderInterface&MockObject
     */
    private ProductSearchBuilderInterface $searchBuilder;

    protected function setUp(): void
    {
        $this->searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $this->listingLoader = $this->createMock(ProductListingLoader::class);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->getProductSearchRoute()->getDecorated();
    }

    public function testLoadWithSearchTerm(): void
    {
        $request = new Request();
        $request->query->set('search', 'test');

        $criteria = new Criteria();

        $this->searchBuilder->expects($this->once())
            ->method('build')
            ->with(
                $request,
                $criteria,
                static::isInstanceOf(SalesChannelContext::class)
            );

        $this->listingLoader->expects($this->once())
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

        $this->getProductSearchRoute()->load(
            $request,
            $salesChannelContext,
            $criteria
        );

        static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
    }

    public function testLoadWithoutSearchTerm(): void
    {
        $request = new Request();
        $criteria = new Criteria();

        $this->searchBuilder->expects($this->never())
            ->method('build');

        $this->listingLoader->expects($this->once())
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

        $this->getProductSearchRoute()->load(
            $request,
            $salesChannelContext,
            $criteria
        );

        static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
    }

    private function getProductSearchRoute(): ProductSearchRoute
    {
        return new ProductSearchRoute(
            $this->searchBuilder,
            $this->listingLoader
        );
    }
}
