<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Event\RouteRequest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Event\RouteRequest\LanguageRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\RouteRequestEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(RouteRequestEvent::class)]
class RouteRequestEventTest extends TestCase
{
    public function testGettersReturnTheConstructorArguments(): void
    {
        $storefrontRequest = new Request();
        $storeApiRequest = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        $event = new LanguageRouteRequestEvent($storefrontRequest, $storeApiRequest, $salesChannelContext, $criteria);

        static::assertSame($storefrontRequest, $event->getStorefrontRequest());
        static::assertSame($storeApiRequest, $event->getStoreApiRequest());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $event->getContext());
        static::assertSame($criteria, $event->getCriteria());
    }

    public function testCriteriaDefaultsToAnEmptyCriteria(): void
    {
        $event = new LanguageRouteRequestEvent(new Request(), new Request(), Generator::generateSalesChannelContext());

        static::assertEquals(new Criteria(), $event->getCriteria());
    }
}
