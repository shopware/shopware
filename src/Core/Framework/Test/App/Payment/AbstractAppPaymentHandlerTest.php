<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Payment;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

abstract class AbstractAppPaymentHandlerTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    public const ERROR_MESSAGE = 'testError';

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * @var ShopIdProvider
     */
    protected $shopIdProvider;

    protected string $shopUrl;

    protected AppEntity $app;

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
    private $paymentMethodRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    private Context $context;

    public function setUp(): void
    {
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->shopUrl = $_SERVER['APP_URL'];
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $this->paymentService = $this->getContainer()->get(PaymentService::class);
        $this->context = Context::createDefaultContext();

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testPayments/manifest.xml');
        $appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $appLifecycle->install($manifest, true, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'testPayments'));
        $appRepository = $this->getContainer()->get('app.repository');

        /** @var AppEntity $app */
        $app = $appRepository->search($criteria, $this->context)->first();
        $this->app = $app;

        $this->resetHistory();
    }

    protected function createCustomer(): string
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

        $this->customerRepository->upsert([$customer], $this->context);

        return $customerId;
    }

    protected function createOrder(string $paymentMethodId): string
    {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $stateId = $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $this->context)->getId();

        $order = [
            'id' => $orderId,
            'orderNumber' => Uuid::randomHex(),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $this->createCustomer(),
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

        $this->orderRepository->upsert([$order], $this->context);

        return $orderId;
    }

    protected function createTransaction(string $orderId, string $paymentMethodId): string
    {
        $id = Uuid::randomHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
            'stateId' => $this->stateMachineRegistry->getInitialState(OrderTransactionStates::STATE_MACHINE, $this->context)->getId(),
            'amount' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $this->orderTransactionRepository->upsert([$transaction], $this->context);

        return $id;
    }

    protected function getPaymentMethodId(string $name): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', sprintf('app\\testPayments_%s', $name)));
        $id = $this->paymentMethodRepository->searchIds($criteria, $this->context)->firstId();
        static::assertNotNull($id);
        static::assertIsString($id);

        return $id;
    }

    protected function getSalesChannelContext(string $paymentMethodId): SalesChannelContext
    {
        return $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            Defaults::SALES_CHANNEL,
            [SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId]
        );
    }

    protected function signResponse(array $content): ResponseInterface
    {
        $json = json_encode($content);

        $secret = $this->app->getAppSecret();

        $hmac = hash_hmac('sha256', $json, $secret);

        $response = new Response(
            200,
            [
                'shopware-app-signature' => $hmac,
            ],
            $json
        );

        return $response;
    }

    protected function assertOrderTransactionState(string $state, string $transactionId): void
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('state');
        $transaction = $this->orderTransactionRepository->search($criteria, $this->context)->first();
        static::assertNotNull($transaction);

        $states = $this->stateMachineRegistry->getStateMachine(OrderTransactionStates::STATE_MACHINE, $this->context)->getStates();
        static::assertNotNull($states);
        $actualState = $states->get($transaction->getStateId());
        static::assertNotNull($actualState);
        static::assertSame($state, $actualState->getTechnicalName());
    }
}
