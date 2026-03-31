<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Checkout\Cart\SalesChannel\DeliveryCostRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryCostRoute::class)]
class DeliveryCostRouteTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $route = new DeliveryCostRoute(
            $this->createMock(EntityRepository::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class),
            new RuleIdMatcher(),
        );

        $this->expectException(DecorationPatternException::class);

        $route->getDecorated();
    }

    public function testDeliveryCostsCartReturnsCurrentAndAlternativeShippingMethods(): void
    {
        $shippingMethod1 = $this->createShippingMethod('shipping-1');
        $shippingMethod2 = $this->createShippingMethod('shipping-2');
        $shippingMethod3 = $this->createShippingMethod('shipping-3');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod1);
        $shippingMethods = new ShippingMethodCollection([$shippingMethod1, $shippingMethod2, $shippingMethod3]);
        $cart = Generator::createCartWithDelivery();

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->atLeast(2))
            ->method('loadByCart')
            ->with(
                static::isInstanceOf(SalesChannelContext::class),
                static::isInstanceOf(Cart::class),
                static::isInstanceOf(CartBehavior::class),
                true,
            )
            ->willReturn(new RuleLoaderResult(Generator::createCartWithDelivery(), new RuleCollection()));

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::isInstanceOf(Request::class),
                $cart,
                $context,
            )
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection([$shippingMethod1, $shippingMethod2, $shippingMethod3]),
                new ErrorCollection(),
            ));

        $route = new DeliveryCostRoute(
            $this->createShippingMethodRepositoryMock($shippingMethods, $context, [$shippingMethod1->getId(), $shippingMethod2->getId(), $shippingMethod3->getId()]),
            $cartRuleLoader,
            $checkoutGatewayRoute,
            new RuleIdMatcher(),
        );

        $response = $route->deliveryCostsCart($cart, $context);

        static::assertCount(3, $response->getDeliveryCosts());
        static::assertNotNull($response->getShippingCost($shippingMethod1->getId()));
        static::assertNotNull($response->getShippingCost($shippingMethod2->getId()));
        static::assertNotNull($response->getShippingCost($shippingMethod3->getId()));
    }

    /**
     * @param non-empty-list<string> $expectedIds
     *
     * @return EntityRepository<ShippingMethodCollection>
     */
    private function createShippingMethodRepositoryMock(
        ShippingMethodCollection $shippingMethods,
        SalesChannelContext $context,
        array $expectedIds,
    ): EntityRepository {
        $searchResult = new EntitySearchResult(
            'shipping_method',
            $shippingMethods->count(),
            $shippingMethods,
            null,
            new Criteria($expectedIds),
            $context->getContext()
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        return $repository;
    }

    private function createShippingMethod(string $id): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($id);
        $shippingMethod->setName('Shipping ' . $id);
        $shippingMethod->setActive(true);

        return $shippingMethod;
    }
}
