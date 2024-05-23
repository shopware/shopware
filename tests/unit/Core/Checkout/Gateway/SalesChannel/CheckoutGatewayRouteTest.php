<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayInterface;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CheckoutGatewayRoute::class)]
#[Package('checkout')]
class CheckoutGatewayRouteTest extends TestCase
{
    public function testDecoratedThrows(): void
    {
        $route = new CheckoutGatewayRoute(
            $this->createMock(AbstractPaymentMethodRoute::class),
            $this->createMock(AbstractShippingMethodRoute::class),
            $this->createMock(CheckoutGatewayInterface::class),
            $this->createMock(RuleIdMatcher::class),
        );

        $this->expectException(DecorationPatternException::class);

        $route->getDecorated();
    }

    public function testLoad(): void
    {
        $request = new Request();
        $cart = new Cart('hatoken');
        $context = Generator::createSalesChannelContext();

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());

        $paymentMethods = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                PaymentMethodDefinition::ENTITY_NAME,
                1,
                new PaymentMethodCollection([$paymentMethod]),
                null,
                new Criteria(),
                $context->getContext()
            )
        );

        $ruleId = Uuid::randomHex();
        $context->getContext()->setRuleIds([$ruleId]);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setAvailabilityRuleId($ruleId);

        $shippingMethods = new ShippingMethodRouteResponse(
            new EntitySearchResult(
                ShippingMethodDefinition::ENTITY_NAME,
                1,
                new ShippingMethodCollection([$shippingMethod]),
                null,
                new Criteria(),
                $context->getContext()
            )
        );

        $paymentMethodRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentMethodRoute
            ->expects(static::once())
            ->method('load')
            ->with($request, $context, static::equalTo((new Criteria())->addAssociation('appPaymentMethod.app')))
            ->willReturn($paymentMethods);

        $shippingMethodRoute = $this->createMock(AbstractShippingMethodRoute::class);
        $shippingMethodRoute
            ->expects(static::once())
            ->method('load')
            ->with($request, $context, static::equalTo((new Criteria())->addAssociation('appShippingMethod.app')))
            ->willReturn($shippingMethods);

        $response = new CheckoutGatewayResponse(
            $paymentMethods->getPaymentMethods(),
            $shippingMethods->getShippingMethods(),
            new ErrorCollection()
        );

        $payload = new CheckoutGatewayPayloadStruct($cart, $context, $paymentMethods->getPaymentMethods(), $shippingMethods->getShippingMethods());

        $checkoutGateway = $this->createMock(CheckoutGatewayInterface::class);
        $checkoutGateway
            ->expects(static::once())
            ->method('process')
            ->with(static::equalTo($payload))
            ->willReturn($response);

        $ruleIdMatcher = $this->createMock(RuleIdMatcher::class);
        $ruleIdMatcher
            ->expects(static::exactly(2))
            ->method('filterCollection')
            ->willReturnArgument(0);

        $route = new CheckoutGatewayRoute($paymentMethodRoute, $shippingMethodRoute, $checkoutGateway, $ruleIdMatcher);
        $result = $route->load($request, $cart, $context);

        static::assertSame($paymentMethods->getPaymentMethods(), $result->getPaymentMethods());
        static::assertSame($shippingMethods->getShippingMethods(), $result->getShippingMethods());
        static::assertSame($response->getCartErrors(), $result->getErrors());
    }

    public function testUnavailableMethodsAddCartError(): void
    {
        $request = new Request();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->addTranslated('name', 'Foo');

        $cart = new Cart('hatoken');
        $cart->addDeliveries(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection(),
                    new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
                    $shippingMethod,
                    new ShippingLocation(new CountryEntity(), null, null),
                    new CalculatedPrice(100.00, 100.00, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ])
        );

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->addTranslated('name', 'Bar');

        $context = Generator::createSalesChannelContext(paymentMethod: $paymentMethod);

        $paymentMethods = new PaymentMethodRouteResponse(
            new EntitySearchResult(
                PaymentMethodDefinition::ENTITY_NAME,
                0,
                new PaymentMethodCollection(),
                null,
                new Criteria(),
                $context->getContext()
            )
        );

        $shippingMethods = new ShippingMethodRouteResponse(
            new EntitySearchResult(
                ShippingMethodDefinition::ENTITY_NAME,
                0,
                new ShippingMethodCollection(),
                null,
                new Criteria(),
                $context->getContext()
            )
        );

        $paymentMethodRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentMethodRoute
            ->expects(static::once())
            ->method('load')
            ->with($request, $context, static::equalTo((new Criteria())->addAssociation('appPaymentMethod.app')))
            ->willReturn($paymentMethods);

        $shippingMethodRoute = $this->createMock(AbstractShippingMethodRoute::class);
        $shippingMethodRoute
            ->expects(static::once())
            ->method('load')
            ->with($request, $context, static::equalTo((new Criteria())->addAssociation('appShippingMethod.app')))
            ->willReturn($shippingMethods);

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $payload = new CheckoutGatewayPayloadStruct($cart, $context, $paymentMethods->getPaymentMethods(), $shippingMethods->getShippingMethods());

        $checkoutGateway = $this->createMock(CheckoutGatewayInterface::class);
        $checkoutGateway
            ->expects(static::once())
            ->method('process')
            ->with(static::equalTo($payload))
            ->willReturn($response);

        $ruleIdMatcher = $this->createMock(RuleIdMatcher::class);
        $ruleIdMatcher
            ->expects(static::exactly(2))
            ->method('filterCollection')
            ->willReturnArgument(0);

        $route = new CheckoutGatewayRoute(
            $paymentMethodRoute,
            $shippingMethodRoute,
            $checkoutGateway,
            $ruleIdMatcher
        );

        $result = $route->load($request, $cart, $context);

        static::assertCount(2, $result->getErrors());

        $error = $result->getErrors()->first();

        static::assertNotNull($error);
        static::assertSame('payment-method-blocked', $error->getMessageKey());
        static::assertSame('Payment method Bar not available. Reason: not allowed', $error->getMessage());

        $error = $result->getErrors()->last();

        static::assertNotNull($error);
        static::assertSame('shipping-method-blocked', $error->getMessageKey());
        static::assertSame('Shipping method Foo not available', $error->getMessage());
    }
}
