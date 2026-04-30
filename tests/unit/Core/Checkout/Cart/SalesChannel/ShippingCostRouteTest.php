<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Checkout\Cart\SalesChannel\ShippingCostRoute;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutPermissions;
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
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingCostRoute::class)]
class ShippingCostRouteTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $route = new ShippingCostRoute(
            $this->createMock(EntityRepository::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class),
        );

        $this->expectException(DecorationPatternException::class);

        $route->getDecorated();
    }

    public function testShippingCostsCartReturnsCurrentAndAlternativeShippingMethods(): void
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
                static::callback(function (Request $request): bool {
                    static::assertTrue($request->request->getBoolean('onlyAvailable'));

                    return true;
                }),
                $cart,
                $context,
            )
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection([$shippingMethod1, $shippingMethod2, $shippingMethod3]),
                new ErrorCollection(),
            ));

        $route = new ShippingCostRoute(
            $this->createShippingMethodRepositoryMock($shippingMethods, $context, [$shippingMethod1->getId(), $shippingMethod2->getId(), $shippingMethod3->getId()]),
            $cartRuleLoader,
            $checkoutGatewayRoute,
        );

        $response = $route->shippingCostsCart($cart, $context);

        static::assertCount(3, $response->getShippingCosts());
        static::assertNotNull($response->getShippingCost($shippingMethod1->getId()));
        static::assertNotNull($response->getShippingCost($shippingMethod2->getId()));
        static::assertNotNull($response->getShippingCost($shippingMethod3->getId()));
    }

    public function testShippingCostsCartDoesNotAllowContextPermissionsToEnableCartPersistence(): void
    {
        $shippingMethod1 = $this->createShippingMethod('shipping-1');
        $shippingMethod2 = $this->createShippingMethod('shipping-2');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod1);

        $cart = Generator::createCartWithDelivery();

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->once())
            ->method('loadByCart')
            ->with(
                static::isInstanceOf(SalesChannelContext::class),
                static::isInstanceOf(Cart::class),
                static::callback(function (CartBehavior $behavior): bool {
                    static::assertTrue($behavior->hasPermission(CheckoutPermissions::SKIP_CART_PERSISTENCE));

                    return true;
                }),
                true,
            )
            ->willReturn(new RuleLoaderResult(Generator::createCartWithDelivery(), new RuleCollection()));

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->never())
            ->method('load');

        $route = new ShippingCostRoute(
            $this->createShippingMethodRepositoryMock(new ShippingMethodCollection([$shippingMethod2]), $context, [$shippingMethod2->getId()]),
            $cartRuleLoader,
            $checkoutGatewayRoute,
        );

        $response = $context->withPermissions(
            [CheckoutPermissions::SKIP_CART_PERSISTENCE => false],
            static fn (SalesChannelContext $context) => $route->shippingCostsCart($cart, $context, [$shippingMethod2->getId()])
        );

        static::assertCount(1, $response->getShippingCosts());
    }

    public function testShippingCostsCartReturnsEmptyWhenCheckoutGatewayReturnsNoShippingMethods(): void
    {
        $shippingMethod = $this->createShippingMethod('shipping-1');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod);
        $cart = new Cart('test');

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(function (Request $request): bool {
                    static::assertTrue($request->request->getBoolean('onlyAvailable'));

                    return true;
                }),
                $cart,
                $context,
            )
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection(),
                new ErrorCollection(),
            ));

        $shippingMethodRepository = $this->createMock(EntityRepository::class);
        $shippingMethodRepository
            ->expects($this->never())
            ->method('search');

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->never())
            ->method('loadByCart');

        $route = new ShippingCostRoute(
            $shippingMethodRepository,
            $cartRuleLoader,
            $checkoutGatewayRoute,
        );

        $response = $route->shippingCostsCart($cart, $context);

        static::assertCount(0, $response->getShippingCosts());
    }

    public function testShippingCostsCartSumsShippingCostsOfAllDeliveries(): void
    {
        $shippingMethod = $this->createShippingMethod('shipping-1');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod);
        $deliveryDate = new DeliveryDate(new \DateTimeImmutable('2024-01-01'), new \DateTimeImmutable('2024-01-03'));
        $cart = $this->createCartWithDeliveries($shippingMethod, $deliveryDate, 5.2, -2.2);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->never())
            ->method('load');

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->never())
            ->method('loadByCart');

        $route = new ShippingCostRoute(
            $this->createShippingMethodRepositoryMock(new ShippingMethodCollection([$shippingMethod]), $context, [$shippingMethod->getId()]),
            $cartRuleLoader,
            $checkoutGatewayRoute,
        );

        $response = $route->shippingCostsCart($cart, $context, [$shippingMethod->getId()]);

        static::assertCount(1, $response->getShippingCosts());
        static::assertEqualsWithDelta(3.0, $response->getShippingCost($shippingMethod->getId())?->getTotalPrice(), 0.001);
        static::assertSame($deliveryDate, $response->getDeliveryDate($shippingMethod->getId()));
    }

    public function testShippingCostsCartSkipsShippingMethodsWithoutDelivery(): void
    {
        $shippingMethod1 = $this->createShippingMethod('shipping-1');
        $shippingMethod2 = $this->createShippingMethod('shipping-2');
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod1);
        $shippingMethods = new ShippingMethodCollection([$shippingMethod1, $shippingMethod2]);
        $cart = new Cart('test');

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->once())
            ->method('loadByCart')
            ->with(
                static::isInstanceOf(SalesChannelContext::class),
                static::isInstanceOf(Cart::class),
                static::isInstanceOf(CartBehavior::class),
                true,
            )
            ->willReturn(new RuleLoaderResult(new Cart('test'), new RuleCollection()));

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->never())
            ->method('load');

        $route = new ShippingCostRoute(
            $this->createShippingMethodRepositoryMock($shippingMethods, $context, [$shippingMethod1->getId(), $shippingMethod2->getId()]),
            $cartRuleLoader,
            $checkoutGatewayRoute,
        );

        $response = $route->shippingCostsCart($cart, $context, [$shippingMethod1->getId(), $shippingMethod2->getId()]);

        static::assertCount(0, $response->getShippingCosts());
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
            ->with(
                static::callback(function (Criteria $criteria) use ($expectedIds): bool {
                    static::assertSame($expectedIds, $criteria->getIds());

                    return true;
                }),
                $context->getContext()
            )
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

    private function createCartWithDeliveries(ShippingMethodEntity $shippingMethod, DeliveryDate $deliveryDate, float ...$shippingCosts): Cart
    {
        $cart = new Cart('test');
        $deliveries = [];

        foreach ($shippingCosts as $shippingCost) {
            $deliveries[] = new Delivery(
                new DeliveryPositionCollection(),
                $deliveryDate,
                $shippingMethod,
                new ShippingLocation(new CountryEntity()),
                new CalculatedPrice($shippingCost, $shippingCost, new CalculatedTaxCollection(), new TaxRuleCollection())
            );
        }

        $cart->setDeliveries(new DeliveryCollection($deliveries));

        return $cart;
    }
}
