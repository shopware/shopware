<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;

/**
 * @internal
 * @covers \Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade
 */
class StorefrontCartFacadeTest extends TestCase
{
    /**
     * @dataProvider getTestData
     *
     * @param array<string> $targets
     */
    public function testGet(array $targets): void
    {
        $storefrontCartFacade = $this->getStorefrontCartFacade($targets);
        $cart = $storefrontCartFacade->get('test', $this->getSalesChannelContext($targets));
        $cartErrors = $cart->getErrors();

        if (\in_array('block-shipping-method', $targets, true)) {
            static::assertCount(
                (int) (!\in_array('shipping-allow-switch', $targets, true) || \in_array('recalculation-cart-block-shipping', $targets, true)),
                $cartErrors->filterInstance(ShippingMethodBlockedError::class)
            );
            static::assertCount(
                (int) (\in_array('shipping-allow-switch', $targets, true) && !\in_array('recalculation-cart-block-shipping', $targets, true)),
                $cartErrors->filterInstance(ShippingMethodChangedError::class)
            );
        }

        if (\in_array('block-payment-method', $targets, true)) {
            static::assertCount(
                (int) (!\in_array('payment-allow-switch', $targets, true) || \in_array('recalculation-cart-block-payment', $targets, true)),
                $cartErrors->filterInstance(PaymentMethodBlockedError::class)
            );
            static::assertCount(
                (int) (\in_array('payment-allow-switch', $targets, true) && !\in_array('recalculation-cart-block-payment', $targets, true)),
                $cartErrors->filterInstance(PaymentMethodChangedError::class)
            );
        }

        if (\in_array('output-cart-equals-input-cart', $targets, true)) {
            static::assertEquals($this->getCart($targets), $cart);
        }
    }

    /**
     * @return array<int, array<int, array<int, string>>>
     */
    public function getTestData(): array
    {
        return [
            [
                ['output-cart-equals-input-cart'],
            ],
            [
                ['block-shipping-method', 'shipping-allow-switch'],
            ],
            [
                ['block-payment-method', 'payment-allow-switch'],
            ],
            [
                [
                    'block-shipping-method', 'shipping-allow-switch',
                    'block-payment-method', 'payment-allow-switch',
                ],
            ],
            [
                ['block-shipping-method'],
            ],
            [
                ['block-payment-method'],
            ],
            [
                ['block-shipping-method', 'block-payment-method'],
            ],
            [
                [
                    'recalculation-cart-block-shipping',
                    'recalculation-cart-block-payment',
                    'block-shipping-method', 'shipping-allow-switch', // only to get through the function
                    'block-payment-method', 'payment-allow-switch', // only to get through the function
                    'output-cart-equals-input-cart',
                ],
            ],
        ];
    }

    /**
     * @param array<string> $targets
     */
    private function getStorefrontCartFacade(array $targets): StorefrontCartFacade
    {
        $cartService = $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturn($this->getCart($targets));

        $blockedShippingMethodSwitcher = $this->createMock(BlockedShippingMethodSwitcher::class);
        $blockedShippingMethodSwitcher->method('switch')->willReturnCallback(
            $this->getBlockedShippingMethodSwitcherCallback($targets)
        );

        $blockedPaymentMethodSwitcher = $this->createMock(BlockedPaymentMethodSwitcher::class);
        $blockedPaymentMethodSwitcher->method('switch')->willReturnCallback(
            $this->getBlockedPaymentMethodSwitcherCallback($targets)
        );

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->method('calculate')->willReturnArgument(0);

        $cartPersister = $this->createMock(CartPersister::class);

        return new StorefrontCartFacade(
            $cartService,
            $blockedShippingMethodSwitcher,
            $blockedPaymentMethodSwitcher,
            $contextSwitchRoute,
            $cartCalculator,
            $cartPersister,
        );
    }

    /**
     * @param array<string> $targets
     */
    private function getCart(array $targets): Cart
    {
        $cart = new Cart('cart-name', 'cart-token');
        $cart->add(
            (new LineItem('line-item-id-1', 'line-item-type-1'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-1')
        )->add(
            (new LineItem('line-item-id-2', 'line-item-type-2'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-2')
        );

        $cart->setErrors($this->getCartErrorCollection($targets));

        return $cart;
    }

    /**
     * @param array<string> $targets
     */
    private function getCartErrorCollection(array $targets): ErrorCollection
    {
        $cartErrors = new ErrorCollection();
        if (\in_array('block-shipping-method', $targets, true)) {
            $cartErrors->add(new ShippingMethodBlockedError('shipping-method-name'));
        }

        if (\in_array('block-payment-method', $targets, true)) {
            $cartErrors->add(new PaymentMethodBlockedError('payment-method-name', ''));
        }

        if (\in_array('change-shipping-method', $targets, true)) {
            $cartErrors->add(new ShippingMethodChangedError('shipping-method-name', 'change-shipping-method-name'));
        }

        if (\in_array('change-payment-method', $targets, true)) {
            $cartErrors->add(new PaymentMethodChangedError('payment-method-name', 'change-payment-method-name'));
        }

        return $cartErrors;
    }

    /**
     * @param array<string> $targets
     */
    private function getSalesChannelContext(array $targets): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('shipping-method-id');
        $shippingMethod->setName('shipping-method-name');

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('payment-method-id');
        $paymentMethod->setName('payment-method-name');

        $salesChannelContext->method('getShippingMethod')->willReturn($shippingMethod);
        $salesChannelContext->method('getPaymentMethod')->willReturn($paymentMethod);

        if (\in_array('block-payment-method', $targets, true) || \in_array('block-shipping-method', $targets, true)) {
            $salesChannelContext->method('assign')->willReturnCallback(
                function ($newMethods) use ($targets): void {
                    if (\in_array('block-shipping-method', $targets, true) && \in_array('shipping-allow-switch', $targets, true)) {
                        static::assertSame('shipping-method-name-new', $newMethods['shippingMethod']->getName());
                    } else {
                        static::assertSame('shipping-method-name', $newMethods['shippingMethod']->getName());
                    }

                    if (\in_array('block-payment-method', $targets, true) && \in_array('payment-allow-switch', $targets, true)) {
                        static::assertSame('payment-method-name-new', $newMethods['paymentMethod']->getName());
                    } else {
                        static::assertSame('payment-method-name', $newMethods['paymentMethod']->getName());
                    }
                }
            );
        }

        return $salesChannelContext;
    }

    /**
     * @param array<string> $targets
     */
    private function getBlockedShippingMethodSwitcherCallback(array $targets): callable
    {
        return function (ErrorCollection $errors, SalesChannelContext $salesChannelContext) use ($targets): ShippingMethodEntity {
            if ((\in_array('block-shipping-method', $targets, true) && !\in_array('shipping-allow-switch', $targets, true)) || !\in_array('block-shipping-method', $targets, true)) {
                return $salesChannelContext->getShippingMethod();
            }

            foreach ($errors as $error) {
                if (!$error instanceof ShippingMethodBlockedError) {
                    continue;
                }

                // Exchange cart blocked warning with notice
                if (!\in_array('recalculation-cart-block-shipping', $targets, true)) {
                    $errors->remove($error->getId());
                }
                $errors->add(new ShippingMethodChangedError(
                    $error->getName(),
                    'shipping-method-name-new'
                ));
            }
            $shippingMethod = new ShippingMethodEntity();
            $shippingMethod->setId('shipping-method-id-new');
            $shippingMethod->setName('shipping-method-name-new');

            return $shippingMethod;
        };
    }

    /**
     * @param array<string> $targets
     */
    private function getBlockedPaymentMethodSwitcherCallback(array $targets): callable
    {
        return function (ErrorCollection $errors, SalesChannelContext $salesChannelContext) use ($targets): PaymentMethodEntity {
            if ((\in_array('block-payment-method', $targets, true) && !\in_array('payment-allow-switch', $targets, true)) || !\in_array('block-payment-method', $targets, true)) {
                return $salesChannelContext->getPaymentMethod();
            }

            foreach ($errors as $error) {
                if (!$error instanceof PaymentMethodBlockedError) {
                    continue;
                }

                // Exchange cart blocked warning with notice
                if (!\in_array('recalculation-cart-block-payment', $targets, true)) {
                    $errors->remove($error->getId());
                }
                $errors->add(new PaymentMethodChangedError(
                    $error->getName(),
                    'payment-method-name-new'
                ));
            }
            $paymentMethod = new PaymentMethodEntity();
            $paymentMethod->setId('payment-method-id-new');
            $paymentMethod->setName('payment-method-name-new');

            return $paymentMethod;
        };
    }
}
