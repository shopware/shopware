<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(StorefrontCartFacade::class)]
class StorefrontCartFacadeTest extends TestCase
{
    public function testGetNoBlockedMethods(): void
    {
        $cart = $this->getCart();
        $cart->setErrors($this->getCartErrorCollection());

        $cartFacade = $this->getStorefrontCartFacade($cart);
        $salesChannelContext = $this->getSalesChannelContext();
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        static::assertEquals($this->getCart(), $returnedCart);
    }

    public function testGetBlockedShippingMethodAllowFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods): void {
                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('fallback-shipping-method-name', $shippingMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnFallbackMethod(...)
        );
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        $filtered = $errorCollection->filterInstance(ShippingMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'newShippingMethodName' => 'fallback-shipping-method-name',
            'oldShippingMethodName' => 'original-shipping-method-name',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true));
        static::assertNotEquals($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentMethodAllowFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(false, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('fallback-payment-method-name', $paymentMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnOriginalMethod(...),
            $this->callbackPaymentMethodSwitcherReturnFallbackMethod(...)
        );
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        $filtered = $errorCollection->filterInstance(PaymentMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame([
            'newPaymentMethodName' => 'fallback-payment-method-name',
            'oldPaymentMethodName' => 'original-payment-method-name',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(false, true));
        static::assertNotEquals($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentAndShippingMethodAllowFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(true, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('fallback-payment-method-name', $paymentMethod->getName());
                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('fallback-shipping-method-name', $shippingMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnFallbackMethod(...),
            $this->callbackPaymentMethodSwitcherReturnFallbackMethod(...)
        );
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        $filtered = $errorCollection->filterInstance(PaymentMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame([
            'newPaymentMethodName' => 'fallback-payment-method-name',
            'oldPaymentMethodName' => 'original-payment-method-name',
        ], $error->getParameters());

        $filtered = $errorCollection->filterInstance(ShippingMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'newShippingMethodName' => 'fallback-shipping-method-name',
            'oldShippingMethodName' => 'original-shipping-method-name',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true, true));
        static::assertNotEquals($controlCart, $returnedCart);
    }

    public function testGetBlockedShippingMethodNoFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods): void {
                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('original-shipping-method-name', $shippingMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade($cart);
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        static::assertCount(0, $errorCollection->filterInstance(ShippingMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(ShippingMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodBlockedError::class, $error);
        static::assertSame([
            'name' => 'original-shipping-method-name',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true));
        static::assertEquals($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentMethodNoFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(false, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('original-payment-method-name', $paymentMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade($cart);
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        static::assertCount(0, $errorCollection->filterInstance(PaymentMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(PaymentMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodBlockedError::class, $error);
        static::assertSame([
            'name' => 'original-payment-method-name',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(false, true));
        static::assertEquals($controlCart, $returnedCart);
    }

    public function testGetUnswitchableCart(): void
    {
        $errorCollection = $this->getCartErrorCollection(true, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('fallback-payment-method-name', $paymentMethod->getName());

                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('fallback-shipping-method-name', $shippingMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherUnswitchableCart(...),
            $this->callbackPaymentMethodSwitcherUnswitchableCart(...)
        );
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        static::assertCount(0, $errorCollection->filterInstance(ShippingMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(ShippingMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodBlockedError::class, $error);
        static::assertSame([
            'name' => 'original-shipping-method-name',
        ], $error->getParameters());

        static::assertCount(0, $errorCollection->filterInstance(PaymentMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(PaymentMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodBlockedError::class, $error);
        static::assertSame([
            'name' => 'original-payment-method-name',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true, true));
        static::assertEquals($controlCart, $returnedCart);
    }

    public function testCartServiceIsCalledTaxedAndWithNoCaching(): void
    {
        $cartService = static::createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('getCart')
            ->with(
                'token',
                static::isInstanceOf(SalesChannelContext::class),
                false,
                true
            );

        $cartFacade = new StorefrontCartFacade(
            $cartService,
            static::createMock(BlockedShippingMethodSwitcher::class),
            static::createMock(BlockedPaymentMethodSwitcher::class),
            static::createMock(ContextSwitchRoute::class),
            static::createMock(CartCalculator::class),
            static::createMock(AbstractCartPersister::class),
            static::createMock(AbstractCheckoutGatewayRoute::class),
        );

        $cartFacade->get(
            'token',
            static::createMock(SalesChannelContext::class),
            false,
            true
        );
    }

    public function testGetWithCheckoutGatewaySwitchesBlockedPaymentMethodToGatewayDefault(): void
    {
        $cart = $this->getCart();
        $paymentMethods = new PaymentMethodCollection([
            $this->createPaymentMethod('gateway-default-payment-method-id', 'Gateway default payment method'),
            $this->createPaymentMethod('gateway-other-payment-method-id', 'Gateway other payment method'),
        ]);
        $shippingMethods = new ShippingMethodCollection([
            $this->createShippingMethod('original-shipping-method-id', 'Original shipping method'),
        ]);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Cart $loadedCart, SalesChannelContext $context) use ($paymentMethods, $shippingMethods): CheckoutGatewayRouteResponse {
                $loadedCart->getErrors()->add(new PaymentMethodBlockedError('original-payment-method-name', 'not allowed'));

                return new CheckoutGatewayRouteResponse($paymentMethods, $shippingMethods, $loadedCart->getErrors());
            });

        $salesChannelContext = $this->getSalesChannelContextWithDefaults(
            defaultPaymentMethodId: 'gateway-default-payment-method-id',
            defaultShippingMethodId: 'original-shipping-method-id',
        );

        $cartFacade = $this->getStorefrontCartFacade(
            cart: $cart,
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), 'token', $salesChannelContext);

        static::assertSame($cart, $result->cart);
        static::assertSame($paymentMethods, $result->gatewayResponse->getPaymentMethods());

        $errors = $result->cart->getErrors();
        static::assertCount(0, $errors->filterInstance(PaymentMethodBlockedError::class));
        $changedErrors = $errors->filterInstance(PaymentMethodChangedError::class);
        static::assertCount(1, $changedErrors);
        $changedError = $changedErrors->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $changedError);
        static::assertSame('original-payment-method-name', $changedError->getOldPaymentMethodName());
        static::assertSame('Gateway default payment method', $changedError->getNewPaymentMethodName());
    }

    public function testGetWithCheckoutGatewayUsesSwitchedContextForGatewayLoad(): void
    {
        $cart = $this->getCart();
        $cart->setErrors($this->getCartErrorCollection(blockPaymentMethod: true));

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Cart $loadedCart, SalesChannelContext $context): CheckoutGatewayRouteResponse {
                static::assertSame('fallback-payment-method-id', $context->getPaymentMethod()->getId());

                return new CheckoutGatewayRouteResponse(
                    new PaymentMethodCollection([
                        $context->getPaymentMethod(),
                    ]),
                    new ShippingMethodCollection([
                        $context->getShippingMethod(),
                    ]),
                    $loadedCart->getErrors(),
                );
            });

        $cartFacade = $this->getStorefrontCartFacade(
            cart: $cart,
            paymentSwitcherCallbackMethod: $this->callbackPaymentMethodSwitcherReturnFallbackMethod(...),
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $cartFacade->getWithCheckoutGateway(new Request(), 'token', $this->getSalesChannelContextWithDefaults(
            defaultPaymentMethodId: 'original-payment-method-id',
            defaultShippingMethodId: 'original-shipping-method-id',
        ));
    }

    public function testGetWithCheckoutGatewaySwitchesBlockedPaymentMethodToFirstGatewayMethodWhenDefaultIsUnavailable(): void
    {
        $cart = $this->getCart();
        $firstGatewayMethod = $this->createPaymentMethod('gateway-first-payment-method-id', 'Gateway first payment method');
        $paymentMethods = new PaymentMethodCollection([
            $firstGatewayMethod,
            $this->createPaymentMethod('gateway-second-payment-method-id', 'Gateway second payment method'),
        ]);
        $shippingMethods = new ShippingMethodCollection([
            $this->createShippingMethod('original-shipping-method-id', 'Original shipping method'),
        ]);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Cart $loadedCart, SalesChannelContext $context) use ($paymentMethods, $shippingMethods): CheckoutGatewayRouteResponse {
                $loadedCart->getErrors()->add(new PaymentMethodBlockedError('original-payment-method-name', 'not allowed'));

                return new CheckoutGatewayRouteResponse($paymentMethods, $shippingMethods, $loadedCart->getErrors());
            });

        $salesChannelContext = $this->getSalesChannelContextWithDefaults(
            defaultPaymentMethodId: 'unavailable-default-payment-method-id',
            defaultShippingMethodId: 'original-shipping-method-id',
        );

        $cartFacade = $this->getStorefrontCartFacade(
            cart: $cart,
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), 'token', $salesChannelContext);

        static::assertSame($cart, $result->cart);
        $changedErrors = $result->cart->getErrors()->filterInstance(PaymentMethodChangedError::class);
        static::assertCount(1, $changedErrors);
        $changedError = $changedErrors->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $changedError);
        static::assertSame($firstGatewayMethod->getTranslation('name'), $changedError->getNewPaymentMethodName());
    }

    public function testGetWithCheckoutGatewayKeepsBlockedPaymentMethodWhenGatewayHasNoFallback(): void
    {
        $cart = $this->getCart();
        $paymentMethods = new PaymentMethodCollection();
        $shippingMethods = new ShippingMethodCollection([
            $this->createShippingMethod('original-shipping-method-id', 'Original shipping method'),
        ]);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Cart $loadedCart, SalesChannelContext $context) use ($paymentMethods, $shippingMethods): CheckoutGatewayRouteResponse {
                $loadedCart->getErrors()->add(new PaymentMethodBlockedError('original-payment-method-name', 'not allowed'));

                return new CheckoutGatewayRouteResponse($paymentMethods, $shippingMethods, $loadedCart->getErrors());
            });

        $salesChannelContext = $this->getSalesChannelContextWithDefaults(
            defaultPaymentMethodId: 'gateway-default-payment-method-id',
            defaultShippingMethodId: 'original-shipping-method-id',
        );

        $cartFacade = $this->getStorefrontCartFacade(
            cart: $cart,
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), 'token', $salesChannelContext);

        static::assertSame($cart, $result->cart);
        static::assertSame('original-payment-method-id', $salesChannelContext->getPaymentMethod()->getId());
        static::assertCount(1, $result->cart->getErrors()->filterInstance(PaymentMethodBlockedError::class));
        static::assertCount(0, $result->cart->getErrors()->filterInstance(PaymentMethodChangedError::class));
    }

    public function testGetWithCheckoutGatewayReturnsGatewayResponseWithoutSwitchWhenNoMethodIsBlocked(): void
    {
        $cart = $this->getCart();
        $paymentMethods = new PaymentMethodCollection([
            $this->createPaymentMethod('original-payment-method-id', 'Original payment method'),
        ]);
        $shippingMethods = new ShippingMethodCollection([
            $this->createShippingMethod('original-shipping-method-id', 'Original shipping method'),
        ]);

        $response = new CheckoutGatewayRouteResponse($paymentMethods, $shippingMethods, $cart->getErrors());

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator
            ->expects($this->never())
            ->method('calculate');

        $salesChannelContext = $this->getSalesChannelContextWithDefaults(
            defaultPaymentMethodId: 'original-payment-method-id',
            defaultShippingMethodId: 'original-shipping-method-id',
        );

        $cartFacade = $this->getStorefrontCartFacade(
            cart: $cart,
            cartCalculator: $cartCalculator,
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), 'token', $salesChannelContext);

        static::assertSame($cart, $result->cart);
        static::assertSame($paymentMethods, $result->gatewayResponse->getPaymentMethods());
        static::assertSame($shippingMethods, $result->gatewayResponse->getShippingMethods());
        static::assertSame($cart->getErrors(), $result->gatewayResponse->getErrors());
        static::assertSame('original-payment-method-id', $salesChannelContext->getPaymentMethod()->getId());
    }

    public function callbackShippingMethodSwitcherReturnOriginalMethod(ErrorCollection $errors, SalesChannelContext $salesChannelContext): ShippingMethodEntity
    {
        return $salesChannelContext->getShippingMethod();
    }

    public function callbackShippingMethodSwitcherReturnFallbackMethod(ErrorCollection $errors, SalesChannelContext $salesChannelContext): ShippingMethodEntity
    {
        foreach ($errors as $error) {
            if (!$error instanceof ShippingMethodBlockedError) {
                continue;
            }

            // Exchange cart blocked warning with notice
            $errors->remove($error->getId());
            $errors->add(new ShippingMethodChangedError(
                $error->getName(),
                'fallback-shipping-method-name'
            ));
        }

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fallback-shipping-method-id');
        $shippingMethod->setName('fallback-shipping-method-name');

        return $shippingMethod;
    }

    public function callbackShippingMethodSwitcherUnswitchableCart(ErrorCollection $errors, SalesChannelContext $salesChannelContext): ShippingMethodEntity
    {
        foreach ($errors as $error) {
            if (!$error instanceof ShippingMethodBlockedError) {
                continue;
            }

            // Exchange cart blocked warning with notice
            $errors->add(new ShippingMethodChangedError(
                $error->getName(),
                'fallback-shipping-method-name'
            ));
        }

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fallback-shipping-method-id');
        $shippingMethod->setName('fallback-shipping-method-name');

        return $shippingMethod;
    }

    public function callbackPaymentMethodSwitcherReturnOriginalMethod(ErrorCollection $errors, SalesChannelContext $salesChannelContext): PaymentMethodEntity
    {
        return $salesChannelContext->getPaymentMethod();
    }

    public function callbackPaymentMethodSwitcherReturnFallbackMethod(ErrorCollection $errors, SalesChannelContext $salesChannelContext): PaymentMethodEntity
    {
        foreach ($errors as $error) {
            if (!$error instanceof PaymentMethodBlockedError) {
                continue;
            }

            // Exchange cart blocked warning with notice
            $errors->remove($error->getId());
            $errors->add(new PaymentMethodChangedError(
                $error->getName(),
                'fallback-payment-method-name'
            ));
        }

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('fallback-payment-method-id');
        $paymentMethod->setName('fallback-payment-method-name');

        return $paymentMethod;
    }

    public function callbackPaymentMethodSwitcherUnswitchableCart(ErrorCollection $errors, SalesChannelContext $salesChannelContext): PaymentMethodEntity
    {
        foreach ($errors as $error) {
            if (!$error instanceof PaymentMethodBlockedError) {
                continue;
            }

            // Exchange cart blocked warning with notice
            $errors->add(new PaymentMethodChangedError(
                $error->getName(),
                'fallback-payment-method-name'
            ));
        }

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('fallback-payment-method-id');
        $paymentMethod->setName('fallback-payment-method-name');

        return $paymentMethod;
    }

    private function getCart(): Cart
    {
        $cart = new Cart('cart-token');
        $cart->add(
            (new LineItem('line-item-id-1', 'line-item-type-1'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-1')
                ->assign(['uniqueIdentifier' => 'line-item-id-1'])
        )->add(
            (new LineItem('line-item-id-2', 'line-item-type-2'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-2')
                ->assign(['uniqueIdentifier' => 'line-item-id-2'])
        );

        return $cart;
    }

    private function getCartErrorCollection(bool $blockShippingMethod = false, bool $blockPaymentMethod = false): ErrorCollection
    {
        $cartErrors = new ErrorCollection();
        if ($blockShippingMethod) {
            $cartErrors->add(
                new ShippingMethodBlockedError(
                    'original-shipping-method-name'
                )
            );
        }

        if ($blockPaymentMethod) {
            $cartErrors->add(
                new PaymentMethodBlockedError(
                    'original-payment-method-name',
                    ''
                )
            );
        }

        return $cartErrors;
    }

    /**
     * @param callable(ErrorCollection, SalesChannelContext): ShippingMethodEntity|null $shippingSwitcherCallbackMethod
     * @param callable(ErrorCollection, SalesChannelContext): PaymentMethodEntity|null $paymentSwitcherCallbackMethod
     */
    private function getStorefrontCartFacade(
        Cart $cart,
        ?callable $shippingSwitcherCallbackMethod = null,
        ?callable $paymentSwitcherCallbackMethod = null,
        ?CartCalculator $cartCalculator = null,
        ?AbstractCheckoutGatewayRoute $checkoutGatewayRoute = null,
    ): StorefrontCartFacade {
        $cartService = $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturn($cart);

        $shippingCallback = $shippingSwitcherCallbackMethod ?? $this->callbackShippingMethodSwitcherReturnOriginalMethod(...);
        $realShippingSwitcher = new BlockedShippingMethodSwitcher($this->createMock(ShippingMethodRoute::class));
        $blockedShippingMethodSwitcher = $this->createMock(BlockedShippingMethodSwitcher::class);
        $blockedShippingMethodSwitcher->method('switch')->willReturnCallback(
            static fn (ErrorCollection $errors, SalesChannelContext $context, ?ShippingMethodCollection $methods = null): ShippingMethodEntity => $methods === null ? $shippingCallback($errors, $context) : $realShippingSwitcher->switch($errors, $context, $methods),
        );

        $paymentCallback = $paymentSwitcherCallbackMethod ?? $this->callbackPaymentMethodSwitcherReturnOriginalMethod(...);
        $realPaymentSwitcher = new BlockedPaymentMethodSwitcher($this->createMock(PaymentMethodRoute::class));
        $blockedPaymentMethodSwitcher = $this->createMock(BlockedPaymentMethodSwitcher::class);
        $blockedPaymentMethodSwitcher->method('switch')->willReturnCallback(
            static fn (ErrorCollection $errors, SalesChannelContext $context, ?PaymentMethodCollection $methods = null): PaymentMethodEntity => $methods === null ? $paymentCallback($errors, $context) : $realPaymentSwitcher->switch($errors, $context, $methods),
        );

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);

        if ($cartCalculator === null) {
            $cartCalculator = $this->createMock(CartCalculator::class);
            $cartCalculator->method('calculate')->willReturnArgument(0);
        }

        $cartPersister = $this->createMock(CartPersister::class);

        return new StorefrontCartFacade(
            $cartService,
            $blockedShippingMethodSwitcher,
            $blockedPaymentMethodSwitcher,
            $contextSwitchRoute,
            $cartCalculator,
            $cartPersister,
            $checkoutGatewayRoute ?? $this->createMock(AbstractCheckoutGatewayRoute::class),
        );
    }

    private function getSalesChannelContextWithDefaults(string $defaultPaymentMethodId, string $defaultShippingMethodId): SalesChannelContext
    {
        $shippingMethod = $this->createShippingMethod('original-shipping-method-id', 'original-shipping-method-name');
        $paymentMethod = $this->createPaymentMethod('original-payment-method-id', 'original-payment-method-name');

        $salesChannelContext = Generator::generateSalesChannelContext(paymentMethod: $paymentMethod, shippingMethod: $shippingMethod);
        $salesChannelContext->getSalesChannel()->setPaymentMethodId($defaultPaymentMethodId);
        $salesChannelContext->getSalesChannel()->setShippingMethodId($defaultShippingMethodId);

        return $salesChannelContext;
    }

    private function createPaymentMethod(string $id, string $name): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId($id);
        $paymentMethod->setName($name);
        $paymentMethod->setTranslated(['name' => $name]);

        return $paymentMethod;
    }

    private function createShippingMethod(string $id, string $name): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($id);
        $shippingMethod->setName($name);
        $shippingMethod->setTranslated(['name' => $name]);

        return $shippingMethod;
    }

    /**
     * @return MockObject&SalesChannelContext
     */
    private function getSalesChannelContext(): MockObject
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('original-shipping-method-id');
        $shippingMethod->setName('original-shipping-method-name');

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('original-payment-method-id');
        $paymentMethod->setName('original-payment-method-name');

        $salesChannelContext->method('getShippingMethod')->willReturn($shippingMethod);
        $salesChannelContext->method('getPaymentMethod')->willReturn($paymentMethod);

        return $salesChannelContext;
    }
}
