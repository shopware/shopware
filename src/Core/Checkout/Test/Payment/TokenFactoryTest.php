<?php declare(strict_types=1);

namespace Shopware\Checkout\Test\Payment;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Checkout\Customer\CustomerRepository;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionRepository;
use Shopware\Checkout\Order\OrderRepository;
use Shopware\Checkout\Payment\Cart\Token\PaymentTransactionTokenFactory;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TokenFactoryTest extends KernelTestCase
{
    const PAYMENT_METHOD_INVOICE = '19D144FFE15F4772860D59FCA7F207C1';

    const COUNTRY_GERMANY = 'BD5E2DCF547E4DF6BB1FF58A554BC69E';

    const COUNTRY_STATE_NRW = '9F834BAD88204D9896F31993624AC74C';
    /**
     * @var PaymentTransactionTokenFactory
     */
    protected $tokenFactory;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ApplicationContext
     */
    protected $applicationContext;

    /**
     * @var \Shopware\Checkout\Order\OrderRepository
     */
    protected $orderRepository;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionRepository
     */
    protected $orderTransactionRepository;

    /**
     * @var Connection
     */
    protected $connection;

    public function setUp()
    {
        self::bootKernel();
        $this->container = self::$kernel->getContainer();

        $this->tokenFactory = $this->container->get(PaymentTransactionTokenFactory::class);
        $this->applicationContext = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $this->connection = $this->container->get(Connection::class);

        $this->orderRepository = $this->container->get(OrderRepository::class);
        $this->customerRepository = $this->container->get(CustomerRepository::class);
        $this->orderTransactionRepository = $this->container->get(OrderTransactionRepository::class);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function testGenerateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->readBasic([$transactionId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $context = ApplicationContext::createDefaultContext(Defaults::TENANT_ID);
        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId),
            $context
        );

        $token = $this->connection->fetchAssoc('SELECT * FROM payment_token WHERE token = ?;', [$tokenIdentifier]);

        self::assertEquals($transactionId, Uuid::fromBytesToHex($token['order_transaction_id']));
        self::assertEquals($tokenIdentifier, $token['token']);
        self::assertGreaterThan(new \DateTime(), new \DateTime($token['expires']));
    }

    /**
     * @throws \Shopware\Checkout\Payment\Exception\InvalidTokenException
     * @throws \Shopware\Checkout\Payment\Exception\TokenExpiredException
     */
    public function testValidateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->readBasic([$transactionId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $context = ApplicationContext::createDefaultContext(Defaults::TENANT_ID);

        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId),
            $context
        );

        $token = $this->tokenFactory->validateToken($tokenIdentifier, $context);

        self::assertEquals($transactionId, $token->getTransactionId());
        self::assertEquals($tokenIdentifier, $token->getToken());
        self::assertGreaterThan(new \DateTime(), $token->getExpires());
    }

    /**
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     * @throws \Shopware\Checkout\Payment\Exception\InvalidTokenException
     */
    public function testInvalidateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->readBasic([$transactionId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $context = ApplicationContext::createDefaultContext(Defaults::TENANT_ID);

        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId),
            $context
        );

        $success = $this->tokenFactory->invalidateToken($tokenIdentifier, $context);

        self::assertTrue($success);
    }

    private function prepare(): string
    {
        $customerId = $this->createCustomer($this->customerRepository, $this->applicationContext);
        $orderId = $this->createOrder($customerId, $this->orderRepository, $this->applicationContext);

        return $this->createTransaction($orderId, $this->orderTransactionRepository, $this->applicationContext);
    }

    private function createTransaction(
        string $orderId,
        OrderTransactionRepository $orderTransactionRepository,
        ApplicationContext $applicationContext
    ): string {
        $id = Uuid::uuid4()->getHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => self::PAYMENT_METHOD_INVOICE,
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_OPEN,
            'amount' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $orderTransactionRepository->upsert([$transaction], $applicationContext);

        return $id;
    }

    private function createOrder(
        string $customerId,
        OrderRepository $orderRepository,
        ApplicationContext $applicationContext
    ) {
        $orderId = Uuid::uuid4()->getHex();

        $order = [
            'id' => $orderId,
            'date' => (new \DateTime())->format('Y-m-d H:i:s'),
            'amountTotal' => 100,
            'amountNet' => 100,
            'positionPrice' => 100,
            'shippingTotal' => 5,
            'shippingNet' => 5,
            'isNet' => true,
            'isTaxFree' => true,
            'customerId' => $customerId,
            'stateId' => Defaults::ORDER_STATE_OPEN,
            'paymentMethodId' => self::PAYMENT_METHOD_INVOICE,
            'currencyId' => Defaults::CURRENCY,
            'applicationId' => Defaults::APPLICATION,
            'billingAddress' => [
                'salutation' => 'mr',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => self::COUNTRY_GERMANY,
                'countryStateId' => self::COUNTRY_STATE_NRW,
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        $orderRepository->upsert([$order], $applicationContext);

        return $orderId;
    }

    private function createCustomer(CustomerRepository $repository, ApplicationContext $context): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => password_hash('shopware', PASSWORD_BCRYPT, ['cost' => 13]),
            'defaultPaymentMethodId' => self::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'applicationId' => Defaults::APPLICATION,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => self::COUNTRY_GERMANY,
                    'salutation' => 'Herr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $repository->upsert([$customer], $context);

        return $customerId;
    }
}
