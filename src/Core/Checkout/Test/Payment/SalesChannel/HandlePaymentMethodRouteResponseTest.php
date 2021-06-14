<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\AsyncTestPaymentHandler as AsyncTestPaymentHandlerV630;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class HandlePaymentMethodRouteResponseTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private EntityRepositoryInterface $orderRepository;

    private EntityRepositoryInterface $customerRepository;

    private EntityRepositoryInterface $orderTransactionRepository;

    private EntityRepositoryInterface $paymentMethodRepository;

    private StateMachineRegistry $stateMachineRegistry;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
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
        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::IS_BLANK_ERROR', $response['errors'][0]['code']);
    }

    public function testRequestRandomOrderId(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/handle-payment',
                [
                    'orderId' => Uuid::randomHex(),
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__INVALID_ORDER_ID', $response['errors'][0]['code']);
    }

    public function testPayOrder(): void
    {
        $paymentMethodId = $this->createPaymentMethodV630($this->ids->getContext());
        $customerId = $this->createCustomer($this->ids->getContext());
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->ids->getContext());
        $this->createTransaction($orderId, $paymentMethodId, $this->ids->getContext());

        $this->browser
            ->request(
                'GET',
                '/store-api/handle-payment',
                [
                    'orderId' => $orderId,
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('redirectUrl', $response);
        static::assertSame(AsyncTestPaymentHandlerV630::REDIRECT_URL, $response['redirectUrl']);
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
