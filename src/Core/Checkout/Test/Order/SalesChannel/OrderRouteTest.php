<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class OrderRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface|null
     */
    private $orderRepository;

    /**
     * @var object|null
     */
    private $orderPersister;

    /**
     * @var object|null
     */
    private $processor;

    /**
     * @var object|null
     */
    private $salesChannelContextFactory;

    /**
     * @var object|null
     */
    private $stateMachineRegistry;

    /**
     * @var string
     */
    private $orderId;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $password;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);

        $this->contextPersister = $this->getContainer()->get(SalesChannelContextPersister::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->orderPersister = $this->getContainer()->get(OrderPersister::class);
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->requestCriteriaBuilder = $this->getContainer()->get(RequestCriteriaBuilder::class);
        $this->email = Uuid::randomHex() . '@example.com';
        $this->password = 'shopware';
        $this->customerId = Uuid::randomHex();
        $this->orderId = $this->createOrder($this->customerId, $this->email, $this->password);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => $this->email,
                    'password' => $this->password,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $newToken = $this->contextPersister->replace($response['contextToken']);
        $this->contextPersister->save(
            $newToken,
            [
                'customerId' => $this->customerId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ]
        );

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $newToken);
    }

    public function testGetOrder(): void
    {
        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/v' . PlatformRequest::API_VERSION . '/order',
                $this->requestCriteriaBuilder->toArray($criteria)
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertEquals($this->orderId, $response['orders']['elements'][0]['id']);
    }

    public function testGetOrderCheckPromotion(): void
    {
        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/v' . PlatformRequest::API_VERSION . '/order',
                array_merge(
                    $this->requestCriteriaBuilder->toArray($criteria),
                    ['checkPromotion' => true]
                )
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertEquals($this->orderId, $response['orders']['elements'][0]['id']);
        static::assertArrayHasKey('paymentChangeable', $response);
        static::assertCount(1, $response['paymentChangeable']);
        static::assertTrue(array_pop($response['paymentChangeable']));
    }

    public function testSetPaymentOrder(): void
    {
        $paymentMethodId = $this->getValidPaymentMethodId();
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/order/payment',
                [
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $paymentMethodId,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('transactions');

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals($paymentMethodId, $order->getTransactions()->last()->getPaymentMethodId());
    }

    public function testSetPaymentOrderWrongPayment(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/order/payment',
                [
                    'orderId' => $this->orderId,
                    'paymentMethodId' => Uuid::randomHex(),
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
    }

    public function testCancelOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/order/state/cancel',
                [
                    'orderId' => $this->orderId,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('technicalName', $response);
        static::assertEquals('cancelled', $response['technicalName']);
    }

    protected function getValidPaymentMethodId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('availabilityRuleId', null))
            ->addFilter(new EqualsFilter('active', true));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    private function createOrder(string $customerId, string $email, string $password): string
    {
        $orderId = Uuid::randomHex();
        $defaultContext = Context::createDefaultContext();
        $orderData = $this->getOrderData($orderId, $customerId, $email, $password, $defaultContext);
        $this->orderRepository->create($orderData, $defaultContext);

        return $orderId;
    }

    private function getOrderData(string $orderId, string $customerId, string $email, string $password, Context $context): array
    {
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        $order = [
            [
                'id' => $orderId,
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context)->getId(),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'deliveries' => [
                    [
                        'stateId' => $this->stateMachineRegistry->getInitialState(OrderDeliveryStates::STATE_MACHINE, $context)->getId(),
                        'shippingMethodId' => $this->getValidShippingMethodId(),
                        'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingDateEarliest' => date(DATE_ISO8601),
                        'shippingDateLatest' => date(DATE_ISO8601),
                        'shippingOrderAddress' => [
                            'salutationId' => $salutation,
                            'firstName' => 'Floy',
                            'lastName' => 'Glover',
                            'zipcode' => '59438-0403',
                            'city' => 'Stellaberg',
                            'street' => 'street',
                            'country' => [
                                'name' => 'kasachstan',
                                'id' => $this->getValidCountryId(),
                            ],
                        ],
                        'positions' => [
                            [
                                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                                'orderLineItemId' => $orderLineItemId,
                            ],
                        ],
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $orderLineItemId,
                        'identifier' => 'test',
                        'quantity' => 1,
                        'type' => 'test',
                        'label' => 'test',
                        'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection(), 2),
                        'good' => true,
                    ],
                ],
                'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
                'orderCustomer' => [
                    'email' => 'test@example.com',
                    'firstName' => 'Noe',
                    'lastName' => 'Hill',
                    'salutationId' => $salutation,
                    'title' => 'Doc',
                    'customerNumber' => 'Test',
                    'customer' => [
                        'id' => $customerId,
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'defaultShippingAddress' => [
                            'id' => $addressId,
                            'firstName' => 'Max',
                            'lastName' => 'Mustermann',
                            'street' => 'Musterstraße 1',
                            'city' => 'Schoöppingen',
                            'zipcode' => '12345',
                            'salutationId' => $this->getValidSalutationId(),
                            'country' => ['name' => 'Germany'],
                        ],
                        'defaultBillingAddressId' => $addressId,
                        'defaultPaymentMethod' => [
                            'name' => 'Invoice',
                            'active' => true,
                            'description' => 'Default payment method',
                            'handlerIdentifier' => SyncTestPaymentHandler::class,
                            'availabilityRule' => [
                                'id' => Uuid::randomHex(),
                                'name' => 'true',
                                'priority' => 0,
                                'conditions' => [
                                    [
                                        'type' => 'cartCartAmount',
                                        'value' => [
                                            'operator' => '>=',
                                            'amount' => 0,
                                        ],
                                    ],
                                ],
                            ],
                            'salesChannels' => [
                                [
                                    'id' => Defaults::SALES_CHANNEL,
                                ],
                            ],
                        ],
                        'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                        'email' => $email,
                        'password' => $password,
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'salutationId' => $this->getValidSalutationId(),
                        'customerNumber' => '12345',
                    ],
                ],
                'billingAddressId' => $addressId,
                'addresses' => [
                    [
                        'salutationId' => $salutation,
                        'firstName' => 'Floy',
                        'lastName' => 'Glover',
                        'zipcode' => '59438-0403',
                        'city' => 'Stellaberg',
                        'street' => 'street',
                        'countryId' => $this->getValidCountryId(),
                        'id' => $addressId,
                    ],
                ],
            ],
        ];

        return $order;
    }
}
