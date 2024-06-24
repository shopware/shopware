<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodRouteResponse::class)]
class ShippingMethodRouteResponseTest extends TestCase
{
    public function testConstruct(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setUniqueIdentifier('foo');

        $result = new EntitySearchResult(
            'shipping-method',
            1,
            $collection = new ShippingMethodCollection([$shippingMethod]),
            null,
            new Criteria(),
            Generator::createSalesChannelContext()->getContext()
        );

        $response = new ShippingMethodRouteResponse($result);

        static::assertSame($collection, $response->getShippingMethods());
    }
}
