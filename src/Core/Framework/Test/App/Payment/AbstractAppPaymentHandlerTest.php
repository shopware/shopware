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
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Checkout\Payment\PreparedPaymentService;
use Shopware\Core\Checkout\Test\Customer\CustomerBuilder;
use Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransaction\OrderTransactionBuilder;
use Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureBuilder;
use Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundBuilder;
use Shopware\Core\Checkout\Test\Order\OrderBuilder;
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
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
abstract class AbstractAppPaymentHandlerTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    public const ERROR_MESSAGE = 'testError';

    protected PaymentService $paymentService;

    protected PreparedPaymentService $preparedPaymentService;

    protected PaymentRefundProcessor $paymentRefundProcessor;

    protected ShopIdProvider $shopIdProvider;

    protected string $shopUrl;

    protected AppEntity $app;

    protected IdsCollection $ids;

    protected EntityRepositoryInterface $orderRepository;

    private EntityRepositoryInterface $customerRepository;

    private EntityRepositoryInterface $paymentMethodRepository;

    private StateMachineRegistry $stateMachineRegistry;

    private InitialStateIdLoader $initialStateIdLoader;

    private AbstractSalesChannelContextFactory $salesChannelContextFactory;

    private EntityRepositoryInterface $orderTransactionRepository;

    private EntityRepositoryInterface $orderTransactionCaptureRepository;

    private EntityRepositoryInterface $orderTransactionCaptureRefundRepository;

    private Context $context;

    public function setUp(): void
    {
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->orderTransactionCaptureRepository = $this->getContainer()->get('order_transaction_capture.repository');
        $this->orderTransactionCaptureRefundRepository = $this->getContainer()->get('order_transaction_capture_refund.repository');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->initialStateIdLoader = $this->getContainer()->get(InitialStateIdLoader::class);
        $this->salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->shopUrl = $_SERVER['APP_URL'];
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $this->paymentService = $this->getContainer()->get(PaymentService::class);
        $this->preparedPaymentService = $this->getContainer()->get(PreparedPaymentService::class);
        $this->paymentRefundProcessor = $this->getContainer()->get(PaymentRefundProcessor::class);
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
        $this->ids = new IdsCollection();

        $this->resetHistory();
    }

    protected function createCustomer(): string
    {
        $customerId = $this->ids->get('customer');
        $addressId = $this->ids->get('address');

        $customer = (new CustomerBuilder($this->ids, '1337'))
            ->firstName('Max')
            ->lastName('Mustermann')
            ->add('id', $this->ids->get('customer'))
            ->add('email', Uuid::randomHex() . '@example.com')
            ->add('salesChannelId', TestDefaults::SALES_CHANNEL)
            ->add('password', 'shopware')
            ->add('defaultPaymentMethodId', $this->getValidPaymentMethodId())
            ->defaultShippingAddress('address')
            ->defaultBillingAddress('address', [
                'id' => $addressId,
                'customerId' => $customerId,
                'countryId' => $this->getValidCountryId(),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
            ])
            ->customerGroup(TestDefaults::FALLBACK_CUSTOMER_GROUP)
            ->build();

        $this->customerRepository->upsert([$customer], $this->context);

        return $customerId;
    }

    protected function createOrder(string $paymentMethodId): string
    {
        $orderId = $this->ids->get('order');
        $addressId = $this->ids->get('address');

        $this->ids->set(
            'state',
            $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE)
        );

        $stateId = $this->ids->get('state');
        $customerId = $this->createCustomer();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->add('id', $this->ids->get('order'))
            ->add('orderDateTime', (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->add('price', new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET))
            ->add('shippingCosts', new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->add('orderCustomer', [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ])
            ->add('stateId', $stateId)
            ->add('paymentMethodId', $paymentMethodId)
            ->add('currencyId', Defaults::CURRENCY)
            ->add('currencyFactor', 1.0)
            ->add('salesChannelId', TestDefaults::SALES_CHANNEL)
            ->addAddress('address', [
                'id' => $addressId,
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => $this->getValidCountryId(),
            ])
            ->add('billingAddressId', $addressId)
            ->add('shippingAddressId', $addressId)
            ->add('context', '{}')
            ->add('payload', '{}')
            ->build();

        $this->orderRepository->upsert([$order], $this->context);

        return $orderId;
    }

    protected function createTransaction(string $orderId, string $paymentMethodId): string
    {
        $this->ids->set(
            'transaction_state',
            $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE)
        );

        $transaction = (new OrderTransactionBuilder($this->ids, 'transaction'))
            ->add('orderId', $orderId)
            ->add('paymentMethodId', $paymentMethodId)
            ->add('stateId', $this->ids->get('transaction_state'))
            ->add('payload', '{}')
            ->amount(100)
            ->build();

        $this->orderTransactionRepository->upsert([$transaction], $this->context);

        return $this->ids->get('transaction');
    }

    protected function createCapture(string $orderTransactionId): string
    {
        $capture = (new OrderTransactionCaptureBuilder($this->ids, 'capture', $orderTransactionId))
            ->build();

        $this->orderTransactionCaptureRepository->upsert([$capture], $this->context);

        return $this->ids->get('capture');
    }

    protected function createRefund(string $captureId): string
    {
        $refund = (new OrderTransactionCaptureRefundBuilder($this->ids, 'refund', $captureId))
            ->build();

        $this->orderTransactionCaptureRefundRepository->upsert([$refund], $this->context);

        return $this->ids->get('refund');
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

    protected function getSalesChannelContext(string $paymentMethodId, ?string $customerId = null): SalesChannelContext
    {
        return $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
                SalesChannelContextService::CUSTOMER_ID => $customerId,
            ]
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

    protected function assertRefundState(string $state, string $refundId): void
    {
        $criteria = new Criteria([$refundId]);
        $criteria->addAssociation('state');
        $refund = $this->orderTransactionCaptureRefundRepository->search($criteria, $this->context)->first();
        static::assertNotNull($refund);

        $states = $this->stateMachineRegistry->getStateMachine(OrderTransactionCaptureRefundStates::STATE_MACHINE, $this->context)->getStates();
        static::assertNotNull($states);
        $actualState = $states->get($refund->getStateId());
        static::assertNotNull($actualState);
        static::assertSame($state, $actualState->getTechnicalName());
    }
}
