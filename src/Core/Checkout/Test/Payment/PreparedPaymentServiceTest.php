<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\CapturePreparedPaymentException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\Exception\ValidatePreparedPaymentException;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PreparedPaymentService;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\PreparedTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 * This test handles transactions itself, because it shuts down the kernel in the setUp method.
 */
#[Package('checkout')]
class PreparedPaymentServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;

    private PreparedPaymentService $paymentService;

    private EntityRepository $orderRepository;

    private EntityRepository $customerRepository;

    private EntityRepository $orderTransactionRepository;

    private EntityRepository $paymentMethodRepository;

    private Context $context;

    private OrderTransactionStateHandler $orderTransactionStateHandler;

    protected function setUp(): void
    {
        // Previous tests may build the local cache of \Shopware\Core\System\StateMachine\StateMachineRegistry, shutdown the Kernel to rebuild the container
        $this->getContainer()->get('kernel')->shutdown();

        PreparedTestPaymentHandler::$preOrderPaymentStruct = null;
        PreparedTestPaymentHandler::$fail = false;

        $this->paymentService = $this->getContainer()->get(PreparedPaymentService::class);
        $this->orderTransactionStateHandler = $this->getContainer()->get(OrderTransactionStateHandler::class);
        $this->orderRepository = $this->getRepository(OrderDefinition::ENTITY_NAME);
        $this->customerRepository = $this->getRepository(CustomerDefinition::ENTITY_NAME);
        $this->orderTransactionRepository = $this->getRepository(OrderTransactionDefinition::ENTITY_NAME);
        $this->paymentMethodRepository = $this->getRepository(PaymentMethodDefinition::ENTITY_NAME);
        $this->context = Context::createDefaultContext();

        $this->getContainer()->get(Connection::class)->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->rollBack();

        // Shutdown the Kernel, to clear the local cache of the \Shopware\Core\System\StateMachine\StateMachineRegistry for following test cases.
        $this->getContainer()->get('kernel')->shutdown();
    }

    public function testHandlePreOrderPayment(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $cart = Generator::createCart();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $struct = $this->paymentService->handlePreOrderPayment($cart, new RequestDataBag(), $salesChannelContext);

        static::assertInstanceOf(ArrayStruct::class, $struct);
        static::assertSame(PreparedTestPaymentHandler::TEST_STRUCT_CONTENT, $struct->all());
    }

    public function testHandlePreOrderPaymentFails(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $cart = Generator::createCart();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        PreparedTestPaymentHandler::$fail = true;

        $this->expectException(ValidatePreparedPaymentException::class);
        $this->paymentService->handlePreOrderPayment($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testHandlePreOrderPaymentNoPaymentHandler(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context, Uuid::randomHex());
        $cart = Generator::createCart();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $this->expectException(UnknownPaymentMethodException::class);
        $this->expectExceptionMessage(\sprintf('The payment method %s could not be found.', $paymentMethodId));
        $this->paymentService->handlePreOrderPayment($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testHandlePostOrderPayment(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $salesChannelContext->getContext());
        $this->createTransaction($orderId, $paymentMethodId, $salesChannelContext->getContext());
        $order = $this->loadOrder($orderId, $salesChannelContext);
        $struct = new ArrayStruct(['testStruct']);

        $this->paymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, $struct);

        static::assertSame($struct, PreparedTestPaymentHandler::$preOrderPaymentStruct);
    }

    public function testHandlePostOrderPaymentWithoutStruct(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $salesChannelContext->getContext());
        $this->createTransaction($orderId, $paymentMethodId, $salesChannelContext->getContext());
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $this->paymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, null);
        static::assertNull(PreparedTestPaymentHandler::$preOrderPaymentStruct);
    }

    public function testHandlePostOrderPaymentFails(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $salesChannelContext->getContext());
        $this->createTransaction($orderId, $paymentMethodId, $salesChannelContext->getContext());
        $order = $this->loadOrder($orderId, $salesChannelContext);
        $struct = new ArrayStruct(['testStruct']);
        PreparedTestPaymentHandler::$fail = true;

        $this->expectException(CapturePreparedPaymentException::class);
        $this->paymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, $struct);
    }

    public function testHandlePostOrderPaymentNoPaymentHandler(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context, Uuid::randomHex());
        $customerId = $this->createCustomer($this->context);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $salesChannelContext->getContext());
        $this->createTransaction($orderId, $paymentMethodId, $salesChannelContext->getContext());
        $order = $this->loadOrder($orderId, $salesChannelContext);
        $struct = new ArrayStruct(['testStruct']);

        $this->expectException(UnknownPaymentMethodException::class);
        $this->expectExceptionMessage(\sprintf('The payment method %s could not be found.', $paymentMethodId));
        $this->paymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, $struct);
    }

    public function testHandlePostOrderPaymentNoTransaction(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $salesChannelContext->getContext());
        $order = $this->loadOrder($orderId, $salesChannelContext);
        $struct = new ArrayStruct(['testStruct']);

        $this->paymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, $struct);
        static::assertNull(PreparedTestPaymentHandler::$preOrderPaymentStruct);
    }

    public function testHandlePostOrderPaymentNoTransactionLoaded(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $salesChannelContext->getContext());
        $order = $this->loadOrder($orderId, $salesChannelContext, false);
        $struct = new ArrayStruct(['testStruct']);

        $this->expectException(InvalidOrderException::class);
        $this->paymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, $struct);
    }

    public function testHandlePostOrderPaymentTransactionNonInitialState(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $salesChannelContext->getContext());
        $transactionId = $this->createTransaction($orderId, $paymentMethodId, $salesChannelContext->getContext());
        $this->orderTransactionStateHandler->process($transactionId, $salesChannelContext->getContext());
        $order = $this->loadOrder($orderId, $salesChannelContext);
        $struct = new ArrayStruct(['testStruct']);

        $this->paymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, $struct);
        static::assertNull(PreparedTestPaymentHandler::$preOrderPaymentStruct);
    }

    private function getSalesChannelContext(string $paymentMethodId): SalesChannelContext
    {
        return $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]);
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
            'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
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

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
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
            'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
            'paymentMethodId' => $paymentMethodId,
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
        string $handlerIdentifier = PreparedTestPaymentHandler::class
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

    private function getRepository(string $entityName): EntityRepository
    {
        $repository = $this->getContainer()->get(\sprintf('%s.repository', $entityName));
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    private function loadOrder(string $orderId, SalesChannelContext $context, bool $withTransactions = true): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria
            ->addAssociation('orderCustomer.customer')
            ->addAssociation('orderCustomer.salutation')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('lineItems.cover')
            ->addAssociation('currency')
            ->addAssociation('addresses.country');
        if ($withTransactions) {
            $criteria
                ->addAssociation('transactions.paymentMethod.appPaymentMethod.app')
                ->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));
        }

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context->getContext())->first();
        static::assertNotNull($order);

        return $order;
    }
}
