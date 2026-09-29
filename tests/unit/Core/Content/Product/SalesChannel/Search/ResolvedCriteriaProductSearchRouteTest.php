<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Extension\ProductSearchRouteExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ResolvedCriteriaProductSearchRoute::class)]
class ResolvedCriteriaProductSearchRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(ProductSearchRouteResponse::class);

        $decorated = $this->createMock(AbstractProductSearchRoute::class);
        $decorated->expects($this->never())->method('load');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('product-search-route.load.pre', static function (ProductSearchRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ResolvedCriteriaProductSearchRoute(
            $decorated,
            new EventDispatcher(),
            new CompositeListingProcessor([]),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria));
    }

    public function testPreListenersRunBeforeTheCriteriaAreResolvedAndPostListenersSeeTheProcessedResult(): void
    {
        $decorated = new SearchRouteStub();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ProductSearchRouteExtension::onPre(), static function (ProductSearchRouteExtension $extension): void {
            $extension->request->query->set('limit', 7);
        });

        $searchFilter = null;
        $dispatcher->addListener(ProductSearchRouteExtension::onPost(), static function (ProductSearchRouteExtension $extension) use (&$searchFilter): void {
            $searchFilter = $extension->result()->getListingResult()->getCurrentFilter('search');
        });

        $route = new ResolvedCriteriaProductSearchRoute(
            $decorated,
            new EventDispatcher(),
            new CompositeListingProcessor([new PagingListingProcessor(new StaticSystemConfigService())]),
            new ExtensionDispatcher($dispatcher),
        );

        $route->load(new Request(['search' => 'foo']), Generator::generateSalesChannelContext(), new Criteria());

        static::assertSame(7, $decorated->criteria?->getLimit());
        static::assertSame('foo', $searchFilter);
    }
}

/**
 * @internal
 */
class SearchRouteStub extends AbstractProductSearchRoute
{
    public ?Criteria $criteria = null;

    public function getDecorated(): AbstractProductSearchRoute
    {
        throw new \LogicException('not decorated');
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        $this->criteria = $criteria;

        return new ProductSearchRouteResponse(
            new ProductListingResult('product', 0, new ProductCollection(), null, $criteria, Context::createDefaultContext())
        );
    }
}
