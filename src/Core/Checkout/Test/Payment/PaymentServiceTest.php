<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactory;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Checkout\Test\Payment\Handler\AsyncTestExceptionPaymentHandler;
use Shopware\Core\Checkout\Test\Payment\Handler\AsyncTestPaymentHandler;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\Request;

class PaymentServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var JWTFactory
     */
    private $tokenFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    protected function setUp(): void
    {
        $this->paymentService = $this->getContainer()->get(PaymentService::class);
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->tokenFactory = $this->getContainer()->get(JWTFactory::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->context = Context::createDefaultContext();
    }

    public function testHandlePaymentByOrderWithInvalidOrderId(): void
    {
        $orderId = Uuid::randomHex();
        $salesChannelContext = Generator::createSalesChannelContext();
        $this->expectException(InvalidOrderException::class);
        $this->expectExceptionMessage(sprintf('The order with id %s is invalid or could not be found.', $orderId));
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testHandlePaymentByOrderSyncPayment(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context, SyncTestPaymentHandler::class);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        static::assertNull($this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext));
    }

    public function testHandlePaymentByOrderAsyncPayment(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);

        static::assertEquals(AsyncTestPaymentHandler::REDIRECT_URL, $response->getTargetUrl());
    }

    public function testHandlePaymentByOrderAsyncPaymentWithFinalize(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);

        static::assertEquals(AsyncTestPaymentHandler::REDIRECT_URL, $response->getTargetUrl());

        $transaction = JWTFactoryTest::createTransaction();
        $transaction->setId($transactionId);
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setOrderId($orderId);

        $token = $this->tokenFactory->generateToken($transaction, 'testFinishUrl');
        $request = new Request();
        $tokenStruct = $this->paymentService->finalizeTransaction($token, $request, $salesChannelContext);

        static::assertSame('testFinishUrl', $tokenStruct->getFinishUrl());
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');
        $transactionEntity = $this->orderTransactionRepository->search($criteria, $this->context)->first();
        static::assertSame(
            OrderTransactionStates::STATE_PAID,
            $transactionEntity->getStateMachineState()->getTechnicalName()
        );
    }

    public function testHandlePaymentByOrderDefaultPayment(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context, DefaultPayment::class);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        static::assertNull($this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext));
    }

    public function testRecoverCartAfterPaymentError(): void
    {
        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $paymentMethodId = $this->createPaymentMethod($this->context, AsyncTestExceptionPaymentHandler::class);
        $customerId = $this->createCustomer($this->context);
        $productId = Uuid::randomHex();
        $this->createProduct($productId, $salesChannelContext);
        $orderId = $this->createOrderWithProductLineItem($customerId, $paymentMethodId, $productId, $this->context);
        $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $this->expectException(AsyncPaymentProcessException::class);
        try {
            $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(['fail' => 1]), $salesChannelContext);
        } catch (AsyncPaymentProcessException $e) {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
            static::assertEquals(1, $cart->getLineItems()->count());
            static::assertEquals($productId, $cart->getLineItems()->first()->getReferencedId());

            throw $e;
        }
    }

    public function testRecoverCartAfterPaymentCancelation(): void
    {
        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $paymentMethodId = $this->createPaymentMethod($this->context, AsyncTestExceptionPaymentHandler::class);
        $customerId = $this->createCustomer($this->context);
        $productId = Uuid::randomHex();
        $this->createProduct($productId, $salesChannelContext);
        $orderId = $this->createOrderWithProductLineItem($customerId, $paymentMethodId, $productId, $this->context);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);

        $transaction = JWTFactoryTest::createTransaction();
        $transaction->setId($transactionId);
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setOrderId($orderId);

        $token = $this->tokenFactory->generateToken($transaction, 'testFinishUrl');
        $request = new Request();

        $this->expectException(CustomerCanceledAsyncPaymentException::class);

        try {
            $this->paymentService->finalizeTransaction($token, $request, $salesChannelContext);
        } catch (CustomerCanceledAsyncPaymentException $e) {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
            static::assertEquals(1, $cart->getLineItems()->count());
            static::assertEquals($productId, $cart->getLineItems()->first()->getReferencedId());

            throw $e;
        }
    }

    public function testFinalizeTransactionWithInvalidToken(): void
    {
        $token = Uuid::randomHex();
        $request = new Request();
        $this->expectException(InvalidTokenException::class);
        $this->paymentService->finalizeTransaction($token, $request, $this->getSalesChannelContext('paymentMethodId'));
    }

    public function testFinalizeTransactionWithExpiredToken(): void
    {
        $request = new Request();
        $transaction = JWTFactoryTest::createTransaction();

        $token = $this->tokenFactory->generateToken($transaction, null, -1);

        $this->expectException(TokenExpiredException::class);
        $this->paymentService->finalizeTransaction($token, $request, $this->getSalesChannelContext('paymentMethodId'));
    }

    public function testFinalizeTransactionCustomerCanceled(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);

        static::assertEquals(AsyncTestPaymentHandler::REDIRECT_URL, $response->getTargetUrl());

        $transaction = JWTFactoryTest::createTransaction();
        $transaction->setId($transactionId);
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setOrderId($orderId);

        $token = $this->tokenFactory->generateToken($transaction, 'testFinishUrl');
        $request = new Request();
        $request->query->set('cancel', true);

        try {
            $this->paymentService->finalizeTransaction($token, $request, $this->getSalesChannelContext($paymentMethodId));
            static::fail('exception should be thrown');
        } catch (CustomerCanceledAsyncPaymentException $e) {
        }
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');

        $transactionEntity = $this->orderTransactionRepository->search($criteria, $this->context)->first();

        static::assertSame(
            OrderStates::STATE_CANCELLED,
            $transactionEntity->getStateMachineState()->getTechnicalName()
        );
    }

    private function getSalesChannelContext(string $paymentMethodId): SalesChannelContext
    {
        return Generator::createSalesChannelContext(
            $this->context,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            (new PaymentMethodEntity())->assign(['id' => $paymentMethodId]),
            $this->getAvailableShippingMethod()
        );
    }

    private function createTransaction(
        string $orderId,
        string $paymentMethodId,
        Context $context
    ): string {
        $id = Uuid::randomHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
            'stateId' => $this->stateMachineRegistry->getInitialState(OrderTransactionStates::STATE_MACHINE, $context)->getId(),
            'amount' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $this->orderTransactionRepository->upsert([$transaction], $context);

        return $id;
    }

    private function createOrder(
        string $customerId,
        string $paymentMethodId,
        Context $context
    ): string {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $stateId = $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context)->getId();

        $order = [
            'id' => $orderId,
            'orderNumber' => 'some-number',
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $paymentMethodId,
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        $this->orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createOrderWithProductLineItem(
        string $customerId,
        string $paymentMethodId,
        string $productId,
        Context $context
    ): string {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $stateId = $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context)->getId();

        $order = [
            'id' => $orderId,
            'orderNumber' => 'some-number',
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $paymentMethodId,
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [
                [
                    'identifier' => Uuid::randomHex(),
                    'referencedId' => $productId,
                    'label' => 'some line item',
                    'price' => [
                        'unitPrice' => 1,
                        'totalPrice' => 1,
                        'quantity' => 1,
                        'taxRules' => [
                            [
                                'taxRate' => 19,
                                'extensions' => [],
                                'percentage' => 100,
                            ],
                        ],
                        'referencePrice' => null,
                        'calculatedTaxes' => [
                            [
                                'tax' => 0,
                                'price' => 0,
                                'taxRate' => 19,
                                'extensions' => [],
                            ],
                        ],
                    ],
                    'quantity' => 1,
                    'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                ],
            ],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        $this->orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createCustomer(Context $context): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], $context);

        return $customerId;
    }

    private function createPaymentMethod(
        Context $context,
        string $handlerIdentifier = AsyncTestPaymentHandler::class
    ): string {
        $id = Uuid::randomHex();
        $payment = [
            'id' => $id,
            'handlerIdentifier' => $handlerIdentifier,
            'name' => 'Test Payment',
            'description' => 'Test payment handler',
            'active' => true,
        ];

        $this->paymentMethodRepository->upsert([$payment], $context);

        return $id;
    }

    private function createProduct(string $productId, SalesChannelContext $salesChannelContext): string
    {
        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['id' => Uuid::randomHex(), 'name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'with id'],
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        $this->addTaxDataToSalesChannel($salesChannelContext, $product['tax']);
        $this->productRepository->upsert([$product], $salesChannelContext->getContext());

        return $product['id'];
    }
}
