<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\AsyncTestPaymentHandler as AsyncTestPaymentHandlerV630;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler as SyncTestPaymentHandlerV630;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\Request;

class PaymentServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var JWTFactoryV2
     */
    private $tokenFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

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

    protected function setUp(): void
    {
        $this->paymentService = $this->getContainer()->get(PaymentService::class);
        $this->tokenFactory = $this->getContainer()->get(JWTFactoryV2::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
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

    public function testHandlePaymentByOrderSyncPaymentV630(): void
    {
        $paymentMethodId = $this->createPaymentMethodV630($this->context, SyncTestPaymentHandlerV630::class);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        static::assertNull($this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext));
    }

    public function testHandlePaymentByOrderAsyncPaymentV630(): void
    {
        $paymentMethodId = $this->createPaymentMethodV630($this->context);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);

        static::assertEquals(AsyncTestPaymentHandlerV630::REDIRECT_URL, $response->getTargetUrl());
    }

    public function testHandlePaymentByOrderAsyncPaymentWithFinalizeV630(): void
    {
        $paymentMethodId = $this->createPaymentMethodV630($this->context);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);

        static::assertEquals(AsyncTestPaymentHandlerV630::REDIRECT_URL, $response->getTargetUrl());

        $transaction = JWTFactoryV2Test::createTransaction();
        $transaction->setId($transactionId);
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setOrderId($orderId);
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl');
        $token = $this->tokenFactory->generateToken($tokenStruct);
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

    public function testHandlePaymentByOrderDefaultPaymentV630(): void
    {
        $paymentMethodId = $this->createPaymentMethodV630($this->context, DefaultPayment::class);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        static::assertNull($this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext));
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
        $transaction = JWTFactoryV2Test::createTransaction();
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), null, -1);
        $token = $this->tokenFactory->generateToken($tokenStruct);

        $this->expectException(TokenExpiredException::class);
        $this->paymentService->finalizeTransaction($token, $request, $this->getSalesChannelContext('paymentMethodId'));
    }

    public function testFinalizeTransactionCustomerCanceledV630(): void
    {
        $paymentMethodId = $this->createPaymentMethodV630($this->context);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);

        static::assertEquals(AsyncTestPaymentHandlerV630::REDIRECT_URL, $response->getTargetUrl());

        $transaction = JWTFactoryV2Test::createTransaction();
        $transaction->setId($transactionId);
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setOrderId($orderId);
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl');
        $token = $this->tokenFactory->generateToken($tokenStruct);
        $request = new Request();
        $request->query->set('cancel', true);

        $response = $this->paymentService->finalizeTransaction($token, $request, $this->getSalesChannelContext($paymentMethodId));

        static::assertNotEmpty($response->getException());

        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');

        $transactionEntity = $this->orderTransactionRepository->search($criteria, $this->context)->first();

        static::assertSame(
            OrderTransactionStates::STATE_FAILED,
            $transactionEntity->getStateMachineState()->getTechnicalName()
        );

        //can fail again
        $response = $this->paymentService->finalizeTransaction($token, $request, $this->getSalesChannelContext($paymentMethodId));

        static::assertNotEmpty($response->getException());

        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');

        $transactionEntity = $this->orderTransactionRepository->search($criteria, $this->context)->first();

        static::assertSame(
            OrderTransactionStates::STATE_FAILED,
            $transactionEntity->getStateMachineState()->getTechnicalName()
        );

        //can success after fail
        $request->query->set('cancel', false);
        $this->paymentService->finalizeTransaction($token, $request, $this->getSalesChannelContext($paymentMethodId));

        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');

        $transactionEntity = $this->orderTransactionRepository->search($criteria, $this->context)->first();

        static::assertSame(
            OrderTransactionStates::STATE_PAID,
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
            (new PaymentMethodEntity())->assign(['id' => $paymentMethodId])
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

    private function createPaymentMethodV630(
        Context $context,
        string $handlerIdentifier = AsyncTestPaymentHandlerV630::class
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
}
