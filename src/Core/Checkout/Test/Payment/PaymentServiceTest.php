<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactory;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Checkout\Test\Payment\Handler\TestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class PaymentServiceTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var JWTFactory
     */
    private $tokenFactory;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var RepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp()
    {
        $this->paymentService = $this->getContainer()->get(PaymentService::class);
        $this->tokenFactory = $this->getContainer()->get(JWTFactory::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
    }

    /**
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function testHandlePaymentByOrderWithInvalidOrderId(): void
    {
        $orderId = Uuid::uuid4()->getHex();
        $checkoutContext = Generator::createContext();
        $this->expectException(InvalidOrderException::class);
        $this->paymentService->handlePaymentByOrder(
            $orderId,
            $checkoutContext
        );
    }

    /**
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function testHandlePaymentByOrder(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $checkoutContext = $this->getCheckoutContext($paymentMethodId);

        $response = $this->paymentService->handlePaymentByOrder(
            $orderId,
            $checkoutContext
        );

        static::assertEquals(TestPaymentHandler::REDIRECT_URL, $response->getTargetUrl());
    }

    /**
     * @throws TokenExpiredException
     * @throws UnknownPaymentMethodException
     */
    public function testFinalizeTransactionWithInvalidToken(): void
    {
        $token = Uuid::uuid4()->getHex();
        $request = new Request();
        $this->expectException(InvalidTokenException::class);
        $this->paymentService->finalizeTransaction(
            $token,
            $request,
            Context::createDefaultContext(Defaults::TENANT_ID)
        );
    }

    /**
     * @throws TokenExpiredException
     * @throws UnknownPaymentMethodException
     */
    public function testFinalizeTransactionWithExpiredToken(): void
    {
        $request = new Request();
        $transaction = JWTFactoryTest::createTransaction();

        $token = $this->tokenFactory->generateToken($transaction, $this->context, -1);

        $this->expectException(TokenExpiredException::class);
        $this->paymentService->finalizeTransaction($token, $request, $this->context);
    }

    private function getCheckoutContext(string $paymentMethodId): CheckoutContext
    {
        return Generator::createContext(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            (new PaymentMethodStruct())->assign(['id' => $paymentMethodId])
        );
    }

    private function createTransaction(
        string $orderId,
        string $paymentMethodId,
        Context $context
    ): string {
        $id = Uuid::uuid4()->getHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_OPEN,
            'amount' => new Price(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
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
        $orderId = Uuid::uuid4()->getHex();

        $order = [
            'id' => $orderId,
            'date' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            'amountTotal' => 100,
            'amountNet' => 100,
            'positionPrice' => 100,
            'shippingTotal' => 5,
            'shippingNet' => 5,
            'isNet' => true,
            'isTaxFree' => true,
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => Defaults::ORDER_STATE_OPEN,
            'paymentMethodId' => $paymentMethodId,
            'currencyId' => Defaults::CURRENCY,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'billingAddress' => [
                'salutation' => 'mr',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => Defaults::COUNTRY,
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
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => 'Herr',
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

    private function createPaymentMethod(Context $context): string
    {
        $id = Uuid::uuid4()->getHex();
        $paypal = [
            'id' => $id,
            'technicalName' => TestPaymentHandler::TECHNICAL_NAME,
            'name' => 'Test Payment',
            'additionalDescription' => 'Test payment handler',
            'class' => TestPaymentHandler::class,
            'active' => true,
        ];

        $this->paymentMethodRepository->upsert([$paypal], $context);

        return $id;
    }
}
