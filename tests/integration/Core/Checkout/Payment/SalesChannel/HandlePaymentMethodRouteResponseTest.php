<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
class HandlePaymentMethodRouteResponseTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCollection>
     */
    private EntityRepository $orderTransactionRepository;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->assignSalesChannelContext($this->browser);

        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
    }

    public function testRequestNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/handle-payment',
                [
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__ROUTING_CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
    }

    public function testRequestRandomOrderId(): void
    {
        $salesChannelId = $this->getSalesChannelApiSalesChannelId();
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email, false, ['salesChannelId' => $salesChannelId]);
        $this->loginWithEmail($email);

        $this->browser->request(
            'GET',
            '/store-api/handle-payment',
            ['orderId' => Uuid::randomHex()]
        );

        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__INVALID_ORDER_ID', $response['errors'][0]['code']);
    }

    public function testRequestWithStrangersOrder(): void
    {
        $salesChannelId = $this->getSalesChannelApiSalesChannelId();
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email, false, ['salesChannelId' => $salesChannelId]);

        $otherCustomerId = $this->createCustomer(Uuid::randomHex() . '@other.example.com', false, ['salesChannelId' => $salesChannelId]);

        $paymentMethodId = $this->createPaymentMethod(Context::createDefaultContext());
        $orderOfOtherCustomer = $this->createOrder($otherCustomerId, $paymentMethodId, Context::createDefaultContext(), $salesChannelId);
        $this->createTransaction($orderOfOtherCustomer, $paymentMethodId, Context::createDefaultContext());

        $this->loginWithEmail($email);

        $this->browser->request(
            'GET',
            '/store-api/handle-payment',
            ['orderId' => $orderOfOtherCustomer]
        );

        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__INVALID_ORDER_ID', $response['errors'][0]['code']);
    }

    public function testPayOrder(): void
    {
        $salesChannelId = $this->getSalesChannelApiSalesChannelId();
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($email, false, ['salesChannelId' => $salesChannelId]);

        $paymentMethodId = $this->createPaymentMethod(Context::createDefaultContext());
        $orderId = $this->createOrder($customerId, $paymentMethodId, Context::createDefaultContext(), $salesChannelId);
        $this->createTransaction($orderId, $paymentMethodId, Context::createDefaultContext());

        $this->loginWithEmail($email);

        $this->browser
            ->request(
                'GET',
                '/store-api/handle-payment',
                [
                    'orderId' => $orderId,
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('redirectUrl', $response);
        static::assertSame(TestPaymentHandler::REDIRECT_URL, $response['redirectUrl']);
    }

    private function loginWithEmail(string $email): void
    {
        $this->browser->request(
            'POST',
            '/store-api/account/login',
            [
                'email' => $email,
                'password' => 'shopware',
            ]
        );
        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken, 'Login should succeed');
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
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
            'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
            'amount' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $this->orderTransactionRepository->upsert([$transaction], $context);

        return $id;
    }

    private function createOrder(
        string $customerId,
        string $paymentMethodId,
        Context $context,
        string $salesChannelId = TestDefaults::SALES_CHANNEL
    ): string {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);

        $order = [
            'id' => $orderId,
            'orderNumber' => Uuid::randomHex(),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
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
            'salesChannelId' => $salesChannelId,
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

    private function createPaymentMethod(
        Context $context,
        string $handlerIdentifier = TestPaymentHandler::class
    ): string {
        $id = Uuid::randomHex();
        $payment = [
            'id' => $id,
            'handlerIdentifier' => $handlerIdentifier,
            'name' => 'Test Payment',
            'technicalName' => 'payment_test',
            'description' => 'Test payment handler',
            'active' => true,
        ];

        $this->paymentMethodRepository->upsert([$payment], $context);

        return $id;
    }
}
