<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Suggest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRouteResponse;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute
 */
class ProductSuggestRouteTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    /**
     * @var ProductSearchBuilderInterface&MockObject
     */
    private ProductSearchBuilderInterface $searchBuilder;

    /**
     * @var ProductListingLoader&MockObject
     */
    private ProductListingLoader $listingLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $this->listingLoader = $this->createMock(ProductListingLoader::class);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->getProductSuggestRoute()->getDecorated();
    }

    public function testLoadThrowsExceptionForMissingSearchParameter(): void
    {
        static::expectException(MissingRequestParameterException::class);

        $this->getProductSuggestRoute()->load(
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
                new EntityCollection([]),
                null,
                $criteria,
                Context::createDefaultContext()
            ));

        $this->searchBuilder->expects(static::once())->method('build');

        $suggestCriteriaEventFired = false;
        $this->eventDispatcher->addListener(
            ProductEvents::PRODUCT_SUGGEST_CRITERIA,
            static function (ProductSuggestCriteriaEvent $event) use (&$suggestCriteriaEventFired): void {
                $suggestCriteriaEventFired = true;

                static::assertTrue(
                    $event->getCriteria()->hasState(Criteria::STATE_ELASTICSEARCH_AWARE),
                    'Criteria should be Elasticsearch aware'
                );
            }
        );

        $suggestResultEventFired = false;
        $this->eventDispatcher->addListener(
            ProductEvents::PRODUCT_SUGGEST_RESULT,
            static function (ProductSuggestResultEvent $event) use (&$suggestResultEventFired): void {
                $suggestResultEventFired = true;

                static::assertTrue(
                    $event->getResult()->getCriteria()->hasState(Criteria::STATE_ELASTICSEARCH_AWARE),
                    'Criteria should be Elasticsearch aware'
                );
            }
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $result = $this->getProductSuggestRoute()->load(
            $request,
            $salesChannelContext,
            $criteria
        );

        static::assertInstanceOf(ProductSuggestRouteResponse::class, $result);

        static::assertTrue(
            $suggestCriteriaEventFired,
            sprintf('Event %s was not fired', ProductEvents::PRODUCT_SUGGEST_CRITERIA)
        );

        static::assertTrue(
            $suggestResultEventFired,
            sprintf('Event %s was not fired', ProductEvents::PRODUCT_SUGGEST_CRITERIA)
        );
    }

    private function getProductSuggestRoute(): ProductSuggestRoute
    {
        return new ProductSuggestRoute(
            $this->searchBuilder,
            $this->eventDispatcher,
            $this->listingLoader
        );
    }
}
