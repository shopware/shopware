<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;

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
