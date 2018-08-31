<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTransactionTokenFactory;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class TokenFactoryTest extends TestCase
{
    use DatabaseTransactionBehaviour,
        KernelTestBehaviour;

    /**
     * @var PaymentTransactionTokenFactory
     */
    protected $tokenFactory;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var RepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var RepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var RepositoryInterface
     */
    protected $orderTransactionRepository;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var RepositoryInterface
     */
    private $countryRepository;

    public function setUp()
    {
        $this->tokenFactory = $this->getContainer()->get(PaymentTransactionTokenFactory::class);
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->countryRepository = $this->getContainer()->get('country.repository');
    }

    /**
     * @throws DBALException
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     */
    public function testGenerateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->read(new ReadCriteria([$transactionId]), Context::createDefaultContext(
            Defaults::TENANT_ID));

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
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
     * @throws InvalidTokenException
     * @throws TokenExpiredException
     */
    public function testValidateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->read(new ReadCriteria([$transactionId]), Context::createDefaultContext(
            Defaults::TENANT_ID));

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId),
            $context
        );

        $token = $this->tokenFactory->getToken($tokenIdentifier, $context);

        self::assertEquals($transactionId, $token->getTransactionId());
        self::assertEquals($tokenIdentifier, $token->getToken());
        self::assertGreaterThan(new \DateTime(), $token->getExpires());
    }

    /**
     * @throws InvalidArgumentException
     * @throws InvalidTokenException
     */
    public function testInvalidateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->read(new ReadCriteria([$transactionId]), Context::createDefaultContext(
            Defaults::TENANT_ID));
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId),
            $context
        );

        $success = $this->tokenFactory->invalidateToken($tokenIdentifier, $context);

        self::assertTrue($success);
    }

    private function prepare(): string
    {
        $customerId = $this->createCustomer($this->customerRepository, $this->context);
        $orderId = $this->createOrder($customerId, $this->orderRepository, $this->context);

        return $this->createTransaction($orderId, $this->orderTransactionRepository, $this->context);
    }

    private function createTransaction(
        string $orderId,
        RepositoryInterface $orderTransactionRepository,
        Context $context
    ): string {
        $id = Uuid::uuid4()->getHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_OPEN,
            'amount' => new Price(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $orderTransactionRepository->upsert([$transaction], $context);

        return $id;
    }

    private function createOrder(
        string $customerId,
        RepositoryInterface $orderRepository,
        Context $context
    ) {
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
            'paymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
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

        $orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createCustomer(RepositoryInterface $repository, Context $context): string
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

        $repository->upsert([$customer], $context);

        return $customerId;
    }
}
