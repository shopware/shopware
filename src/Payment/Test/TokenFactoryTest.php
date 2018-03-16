<?php declare(strict_types=1);

namespace Shopware\Payment\Test;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Shopware\Framework\Struct\Uuid;
use Shopware\Api\Customer\Repository\CustomerRepository;
use Shopware\Api\Order\Repository\OrderRepository;
use Shopware\Api\Order\Repository\OrderTransactionRepository;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Payment\Token\PaymentTransactionTokenFactory;
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
     * @var ShopContext
     */
    protected $shopContext;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var OrderTransactionRepository
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
        $this->shopContext = ShopContext::createDefaultContext();
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

        $transactions = $this->orderTransactionRepository->readBasic([$transactionId], ShopContext::createDefaultContext());

        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId)
        );

        $token = $this->connection->fetchAssoc('SELECT * FROM payment_token WHERE token = ?;', [$tokenIdentifier]);

        self::assertEquals($transactionId, Uuid::fromBytesToHex($token['transaction_id']));
        self::assertEquals($tokenIdentifier, $token['token']);
        self::assertGreaterThan(new \DateTime(), new \DateTime($token['expires']));
    }

    /**
     * @throws \Shopware\Payment\Exception\InvalidTokenException
     * @throws \Shopware\Payment\Exception\TokenExpiredException
     */
    public function testValidateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->readBasic([$transactionId], ShopContext::createDefaultContext());

        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId)
        );

        $token = $this->tokenFactory->validateToken($tokenIdentifier);

        self::assertEquals($transactionId, $token->getTransactionId());
        self::assertEquals($tokenIdentifier, $token->getToken());
        self::assertGreaterThan(new \DateTime(), $token->getExpires());
    }

    /**
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     * @throws \Shopware\Payment\Exception\InvalidTokenException
     */
    public function testInvalidateToken()
    {
        $transactionId = $this->prepare();

        $transactions = $this->orderTransactionRepository->readBasic([$transactionId], ShopContext::createDefaultContext());

        $tokenIdentifier = $this->tokenFactory->generateToken(
            $transactions->get($transactionId)
        );

        $success = $this->tokenFactory->invalidateToken($tokenIdentifier);

        self::assertTrue($success);
    }

    private function prepare(): string
    {
        $customerId = $this->createCustomer($this->customerRepository, $this->shopContext);
        $orderId = $this->createOrder($customerId, $this->orderRepository, $this->shopContext);

        return $this->createTransaction($orderId, $this->orderTransactionRepository, $this->shopContext);
    }

    private function createTransaction(
        string $orderId,
        OrderTransactionRepository $orderTransactionRepository,
        ShopContext $shopContext
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

        $orderTransactionRepository->upsert([$transaction], $shopContext);

        return $id;
    }

    private function createOrder(
        string $customerId,
        OrderRepository $orderRepository,
        ShopContext $shopContext
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
            'shopId' => Defaults::SHOP,
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

        $orderRepository->upsert([$order], $shopContext);

        return $orderId;
    }

    private function createCustomer(CustomerRepository $repository, ShopContext $context): string
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
            'shopId' => Defaults::SHOP,
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
