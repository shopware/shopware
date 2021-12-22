<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;

class StorefrontCartFacadeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private StorefrontCartFacade $storefrontCartFacade;

    /**
     * @var MockObject|CartService
     */
    private $cartServiceMock;

    private IdsCollection $salesChannelIds;

    private IdsCollection $availabilityRuleIds;

    protected function setUp(): void
    {
        $container = $this->getContainer();
        $this->cartServiceMock = $this->createMock(CartService::class);
        $this->storefrontCartFacade = new StorefrontCartFacade(
            $this->cartServiceMock,
            $container->get(BlockedShippingMethodSwitcher::class),
            $container->get(BlockedPaymentMethodSwitcher::class),
            $container->get(ContextSwitchRoute::class),
            $container->get(CartCalculator::class),
            $container->get(CartPersister::class)
        );

        $salesChannelId = Uuid::randomHex();
        $salesChannelRepository = $container->get('sales_channel.repository');
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getValidShippingMethodId();
        $salesChannelRepository->create([
            [
                'id' => $salesChannelId,
                'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'currencyId' => Defaults::CURRENCY,
                'paymentMethodId' => $paymentMethodId,
                'shippingMethodId' => $shippingMethodId,
                'countryId' => $this->getValidCountryId(),
                'navigationCategoryId' => $this->getValidCategoryId(),
                'accessKey' => 'PHPUnit',
                'name' => 'PHPUnit',
                'homeEnabled' => false,
                'languages' => [
                    [
                        'id' => Defaults::LANGUAGE_SYSTEM,
                    ],
                ],
            ],
        ], Context::createDefaultContext());
        $shippingMethodRepository = $container->get('shipping_method.repository');
        $shippingMethodRepository->update([
            [
                'id' => $shippingMethodId,
                'salesChannels' => [
                    [
                        'id' => $salesChannelId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $paymentMethodRepository = $container->get('payment_method.repository');
        $paymentMethodRepository->update([
            [
                'id' => $paymentMethodId,
                'salesChannels' => [
                    [
                        'id' => $salesChannelId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->salesChannelIds = new IdsCollection();
        $this->salesChannelIds->set('default', TestDefaults::SALES_CHANNEL);
        $this->salesChannelIds->set('oneMethodEach', $salesChannelId);

        $shippingMethodRepository = $container->get('shipping_method.repository');
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('name', 'Express')
        );

        /** @var ShippingMethodEntity|null $express */
        $express = $shippingMethodRepository->search($criteria, Context::createDefaultContext())->first();
        static::assertNotNull($express);

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('name', 'Standard')
        );

        /** @var ShippingMethodEntity|null $express */
        $standard = $shippingMethodRepository->search($criteria, Context::createDefaultContext())->first();
        static::assertNotNull($standard);

        $this->availabilityRuleIds = new IdsCollection();
        $this->availabilityRuleIds->set('Express', $express->getAvailabilityRuleId());
        $this->availabilityRuleIds->set('Standard', $standard->getAvailabilityRuleId());
    }

    /**
     * @dataProvider providerTestGet
     */
    public function testGet(
        string $salesChannelIdKey,
        bool $blockShippingMethod,
        bool $blockPaymentMethod,
        bool $shouldSwitchShippingMethod,
        bool $shouldSwitchPaymentMethod
    ): void {
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            $this->salesChannelIds->get($salesChannelIdKey)
        );

        $salesChannelContext->setRuleIds(\array_unique($this->availabilityRuleIds->all()));

        $cart = new Cart('PHPUnit', Uuid::randomHex());
        $cartErrors = $cart->getErrors();
        if ($blockShippingMethod) {
            $cartErrors->add(
                new ShippingMethodBlockedError($salesChannelContext->getShippingMethod()->getName())
            );
        }

        if ($blockPaymentMethod) {
            $cartErrors->add(
                new PaymentMethodBlockedError($salesChannelContext->getPaymentMethod()->getName())
            );
        }

        $this->cartServiceMock->method('getCart')->willReturn($cart);
        $this->cartServiceMock->method('recalculate')->willReturn($cart);

        $unblockedCart = $this->storefrontCartFacade->get(
            'PHPUnit',
            $salesChannelContext
        );
        $cartErrors = $unblockedCart->getErrors();

        $paymentMethodBlockedCount = 0;
        $shippingMethodBlockedCount = 0;
        foreach ($cartErrors as $error) {
            if ($error instanceof PaymentMethodBlockedError) {
                ++$paymentMethodBlockedCount;
            }

            if ($error instanceof ShippingMethodBlockedError) {
                ++$shippingMethodBlockedCount;
            }
        }

        if ($shouldSwitchShippingMethod) {
            static::assertSame(0, $shippingMethodBlockedCount);
            $changedShippingMethodErrors = $cartErrors->filterInstance(ShippingMethodChangedError::class);
            static::assertCount(1, $changedShippingMethodErrors);
            /** @var ShippingMethodChangedError $shippingMethodChangedError */
            $shippingMethodChangedError = $changedShippingMethodErrors->first();
            static::assertSame($salesChannelContext->getShippingMethod()->getName(), $shippingMethodChangedError->getOldShippingMethodName());
            static::assertNotSame($salesChannelContext->getShippingMethod()->getName(), $shippingMethodChangedError->getNewShippingMethodName());
        } elseif ($blockShippingMethod) {
            static::assertSame(1, $shippingMethodBlockedCount);
        }

        if ($shouldSwitchPaymentMethod) {
            static::assertSame(0, $paymentMethodBlockedCount);
            $changedPaymentMethodErrors = $cartErrors->filterInstance(PaymentMethodChangedError::class);
            static::assertCount(1, $changedPaymentMethodErrors);
            /** @var PaymentMethodChangedError $paymentMethodChangedError */
            $paymentMethodChangedError = $changedPaymentMethodErrors->first();
            static::assertSame($salesChannelContext->getPaymentMethod()->getName(), $paymentMethodChangedError->getOldPaymentMethodName());
            static::assertNotSame($salesChannelContext->getPaymentMethod()->getName(), $paymentMethodChangedError->getNewPaymentMethodName());
        } elseif ($blockPaymentMethod) {
            static::assertSame(1, $paymentMethodBlockedCount);
        }
    }

    public function providerTestGet(): array
    {
        return [
            [
                'default',
                true,
                false,
                true,
                false,
            ],
            [
                'default',
                false,
                true,
                false,
                true,
            ],
            [
                'default',
                false,
                false,
                false,
                false,
            ],
            [
                'oneMethodEach',
                true,
                true,
                false,
                false,
            ],
            [
                'oneMethodEach',
                false,
                false,
                false,
                false,
            ],
        ];
    }

    public function testGetUnswitchableCart(): void
    {
        /** @var CartCalculator|MockObject $cartCalculatorMock */
        $cartCalculatorMock = $this->createMock(CartCalculator::class);
        $shippingSwitcherMock = $this->createMock(BlockedShippingMethodSwitcher::class);
        $paymentSwitcherMock = $this->createMock(BlockedPaymentMethodSwitcher::class);
        $container = $this->getContainer();
        $storefrontCartFacade = new StorefrontCartFacade(
            $this->cartServiceMock,
            $shippingSwitcherMock,
            $paymentSwitcherMock,
            $container->get(ContextSwitchRoute::class),
            $cartCalculatorMock,
            $container->get(CartPersister::class)
        );

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            $this->salesChannelIds->get('default')
        );

        $salesChannelContext->setRuleIds(\array_unique($this->availabilityRuleIds->all()));

        $cart = new Cart('PHPUnit', Uuid::randomHex());
        $cartErrors = $cart->getErrors();
        $cartErrors->add(
            new ShippingMethodBlockedError($salesChannelContext->getShippingMethod()->getName())
        );

        $cartErrors->add(
            new PaymentMethodBlockedError($salesChannelContext->getPaymentMethod()->getName())
        );

        $cartErrors->add(
            new ShippingMethodChangedError('foo', 'bar')
        );

        $cartErrors->add(
            new PaymentMethodChangedError('buz', 'biz')
        );

        $shipping = new ShippingMethodEntity();
        $shipping->setId(Uuid::randomHex());
        $payment = new PaymentMethodEntity();
        $payment->setId(Uuid::randomHex());
        $this->cartServiceMock->method('getCart')->willReturn($cart);
        $cartCalculatorMock->method('calculate')->willReturn($cart);
        $shippingSwitcherMock->method('switch')->willReturn($shipping);
        $paymentSwitcherMock->method('switch')->willReturn($payment);

        $unblockedCart = $storefrontCartFacade->get(
            'PHPUnit',
            $salesChannelContext
        );
        $cartErrors = $unblockedCart->getErrors();

        $paymentMethodBlockedCount = 0;
        $shippingMethodBlockedCount = 0;
        foreach ($cartErrors as $error) {
            if ($error instanceof PaymentMethodBlockedError) {
                ++$paymentMethodBlockedCount;
            }

            if ($error instanceof ShippingMethodBlockedError) {
                ++$shippingMethodBlockedCount;
            }
        }

        static::assertSame(1, $shippingMethodBlockedCount);
        static::assertSame(1, $paymentMethodBlockedCount);
    }
}
