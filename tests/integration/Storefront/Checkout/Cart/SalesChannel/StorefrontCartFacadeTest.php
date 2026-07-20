<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
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
#[Package('checkout')]
class StorefrontCartFacadeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelContext $context;

    private CartService $cartService;

    private StorefrontCartFacade $cartFacade;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->cartFacade = $this->getContainer()->get(StorefrontCartFacade::class);
    }

    public function testBlockedShippingMethodMessage(): void
    {
        $id = $this->changeShippingMethodAvailabilityRuleId();

        static::getContainer()->get(SalesChannelContextPersister::class)
            ->save($this->ids->get('token'), ['shippingMethodId' => $id], TestDefaults::SALES_CHANNEL);

        $this->context = self::getContainer()
            ->get(SalesChannelContextService::class)
            ->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $this->ids->get('token')));

        $cart = new Cart($this->ids->get('token'));
        $cart = $this->cartService->add($cart, $this->createProduct(), $this->context);

        static::assertInstanceOf(ShippingMethodBlockedError::class, $cart->getErrors()->first());

        $result = $this->cartFacade->get($this->ids->get('token'), $this->context);

        static::assertCount(1, $result->getErrors());

        $error = $result->getErrors()->first();

        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame('Standard', $error->getOldShippingMethodName());
        static::assertSame('Express', $error->getNewShippingMethodName());

        $this->context = self::getContainer()
            ->get(SalesChannelContextService::class)
            ->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $this->ids->get('token')));

        static::assertSame('Express', $this->context->getShippingMethod()->getName());
    }

    public function testBlockedPaymentMethodMessage(): void
    {
        $this->changePaymentMethodAvailabilityRuleId();

        $this->context = self::getContainer()
            ->get(SalesChannelContextService::class)
            ->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $this->ids->get('token')));

        static::assertSame('Cash on delivery', $this->context->getPaymentMethod()->getName());

        $cart = new Cart($this->ids->get('token'));
        $cart = $this->cartService->add($cart, $this->createProduct(), $this->context);

        static::assertInstanceOf(PaymentMethodBlockedError::class, $cart->getErrors()->first());

        $result = $this->cartFacade->get($this->ids->get('token'), $this->context);

        static::assertCount(1, $result->getErrors());

        $error = $result->getErrors()->first();

        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame('Cash on delivery', $error->getOldPaymentMethodName());
        static::assertSame('Paid in advance', $error->getNewPaymentMethodName());

        $this->context = self::getContainer()
            ->get(SalesChannelContextService::class)
            ->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $this->ids->get('token')));

        static::assertSame('Paid in advance', $this->context->getPaymentMethod()->getName());
    }

    public function testCheckoutGatewayBlockedPaymentMethodSwitchesToDefaultPaymentMethod(): void
    {
        $cashOnDeliveryPaymentMethod = $this->getPaymentMethodByTechnicalName('payment_cashpayment');
        $paidInAdvancePaymentMethod = $this->getPaymentMethodByTechnicalName('payment_prepayment');

        static::getContainer()->get('sales_channel.repository')->update([
            [
                'id' => TestDefaults::SALES_CHANNEL,
                'paymentMethodId' => $paidInAdvancePaymentMethod->getId(),
            ],
        ], Context::createDefaultContext());

        static::getContainer()->get(SalesChannelContextPersister::class)
            ->save($this->ids->get('token'), ['paymentMethodId' => $cashOnDeliveryPaymentMethod->getId()], TestDefaults::SALES_CHANNEL);

        $this->context = self::getContainer()
            ->get(SalesChannelContextService::class)
            ->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $this->ids->get('token')));

        static::assertSame($cashOnDeliveryPaymentMethod->getId(), $this->context->getPaymentMethod()->getId());

        $cart = new Cart($this->ids->get('token'));
        $this->cartService->add($cart, $this->createProduct(), $this->context);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Cart $cart, SalesChannelContext $context) use ($cashOnDeliveryPaymentMethod, $paidInAdvancePaymentMethod): CheckoutGatewayRouteResponse {
                $cart->getErrors()->add(new PaymentMethodBlockedError(
                    id: $cashOnDeliveryPaymentMethod->getId(),
                    name: (string) $cashOnDeliveryPaymentMethod->getTranslation('name'),
                    reason: 'not allowed',
                ));

                return new CheckoutGatewayRouteResponse(
                    new PaymentMethodCollection([$paidInAdvancePaymentMethod]),
                    new ShippingMethodCollection([$context->getShippingMethod()]),
                    $cart->getErrors(),
                );
            });

        $cartFacade = new StorefrontCartFacade(
            $this->cartService,
            static::getContainer()->get(BlockedShippingMethodSwitcher::class),
            static::getContainer()->get(BlockedPaymentMethodSwitcher::class),
            static::getContainer()->get(ContextSwitchRoute::class),
            static::getContainer()->get(CartCalculator::class),
            static::getContainer()->get(CartPersister::class),
            $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), $this->ids->get('token'), $this->context);

        static::assertSame($paidInAdvancePaymentMethod->getId(), $this->context->getPaymentMethod()->getId());
        static::assertCount(0, $result->cart->getErrors()->filterInstance(PaymentMethodBlockedError::class));
        static::assertCount(1, $result->cart->getErrors()->filterInstance(PaymentMethodChangedError::class));

        $persistedContext = self::getContainer()
            ->get(SalesChannelContextService::class)
            ->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $this->ids->get('token')));

        static::assertSame($paidInAdvancePaymentMethod->getId(), $persistedContext->getPaymentMethod()->getId());
    }

    private function changeShippingMethodAvailabilityRuleId(): string
    {
        $ruleId = $this->createRule();

        $shippingMethodeRepository = $this->getContainer()->get('shipping_method.repository');

        $criteria = (new Criteria())->addFilter(new EqualsFilter('name', 'Standard'));
        $shippingMethod = $shippingMethodeRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);

        $shippingMethodeRepository->update([['id' => $shippingMethod->getId(), 'availabilityRuleId' => $ruleId]], Context::createDefaultContext());

        return $shippingMethod->getId();
    }

    private function changePaymentMethodAvailabilityRuleId(): string
    {
        $ruleId = $this->createRule();

        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', 'Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment'));
        $paymentMethod = $paymentMethodRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);

        $paymentMethodRepository->update([['id' => $paymentMethod->getId(), 'availabilityRuleId' => $ruleId]], Context::createDefaultContext());

        return $paymentMethod->getId();
    }

    private function getPaymentMethodByTechnicalName(string $technicalName): PaymentMethodEntity
    {
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName));
        $paymentMethod = $paymentMethodRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);

        return $paymentMethod;
    }

    private function createProduct(): LineItem
    {
        $productRepository = $this->getContainer()->get('product.repository');

        $standardTaxId = static::getContainer()->get('tax.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('taxRate', 19.0)), Context::createDefaultContext())->firstId();

        static::assertNotNull($standardTaxId);

        $this->ids->set('tax-id', $standardTaxId);

        $product = new ProductBuilder($this->ids, 'product-1');
        $product->price(10);
        $product->visibility();
        $product->tax('tax-id');

        $productRepository->create([$product->build()], Context::createDefaultContext());

        $productFactory = $this->getContainer()->get(LineItemFactoryRegistry::class);

        return $productFactory->create(
            ['type' => 'product', 'id' => $this->ids->get('product-1'), 'referencedId' => $this->ids->get('product-1')],
            $this->context
        );
    }

    private function createRule(): string
    {
        $ruleId = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->upsert([[
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [[
                'type' => 'cartCartAmount',
                'value' => [
                    'amount' => 120,
                    'operator' => '>',
                ],
            ]],
        ]], Context::createDefaultContext());

        return $ruleId;
    }
}
