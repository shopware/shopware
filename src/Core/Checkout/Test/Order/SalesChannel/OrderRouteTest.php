<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @group slow
 * @group store-api
 */
class OrderRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use MailTemplateTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

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

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $defaultPaymentMethodId;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => Defaults::SALES_CHANNEL,
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
        $this->defaultPaymentMethodId = $this->getValidPaymentMethods()->first()->getId();
        $this->orderId = $this->createOrder($this->customerId, $this->email, $this->password);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $this->email,
                    'password' => $this->password,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        /** @var AbstractSalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create($response['contextToken'], Defaults::SALES_CHANNEL);

        $newToken = $this->contextPersister->replace($response['contextToken'], $salesChannelContext);
        $this->contextPersister->save(
            $newToken,
            [
                'customerId' => $this->customerId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            Defaults::SALES_CHANNEL,
            $this->customerId
        );

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $newToken);
    }

    public function testGetOrder(): void
    {
        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria)
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertEquals($this->orderId, $response['orders']['elements'][0]['id']);
    }

    public function testGetOrderShowsValidDocuments(): void
    {
        $this->createDocument($this->orderId);

        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria)
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey('documents', $response['orders']['elements'][0]);
        static::assertCount(1, $response['orders']['elements'][0]['documents']);
    }

    public function testGetOrderDoesNotShowUnAvailableDocuments(): void
    {
        $this->createDocument($this->orderId, false);

        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria)
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey('documents', $response['orders']['elements'][0]);
        static::assertCount(0, $response['orders']['elements'][0]['documents']);
    }

    public function testGetOrderDoesNotShowHasNotSentDocument(): void
    {
        $this->createDocument($this->orderId, true, false);

        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria)
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey('documents', $response['orders']['elements'][0]);
        static::assertCount(0, $response['orders']['elements'][0]['documents']);
    }

    public function testGetOrderCheckPromotion(): void
    {
        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'POST',
                '/store-api/order',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(array_merge(
                    $this->requestCriteriaBuilder->toArray($criteria),
                    ['checkPromotion' => true]
                ))
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
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $this->defaultPaymentMethodId,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('transactions');

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $this->context)->first();

        static::assertEquals($this->defaultPaymentMethodId, $order->getTransactions()->last()->getPaymentMethodId());
    }

    public function testSetAnotherPaymentMethodToOrder(): void
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('The payment for your order with Storefront is cancelled', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('Message: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $defaultPaymentMethodId = $this->defaultPaymentMethodId;
        $newPaymentMethodId = $this->getValidPaymentMethods()->filter(function (PaymentMethodEntity $paymentMethod) use ($defaultPaymentMethodId) {
            return $paymentMethod->getId() !== $defaultPaymentMethodId;
        })->first()->getId();

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $newPaymentMethodId,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testSetSamePaymentMethodToOrder(): void
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('The payment for your order with Storefront is cancelled', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('Message: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $this->defaultPaymentMethodId,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertFalse($eventDidRun, 'The mail.sent did not run');
    }

    public function testSetPaymentOrderWrongPayment(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
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
                '/store-api/order/state/cancel',
                [
                    'orderId' => $this->orderId,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('technicalName', $response);
        static::assertEquals('cancelled', $response['technicalName']);
    }

    public function testOrderSalesChannelRestriction(): void
    {
        $testChannel = $this->createSalesChannel([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://foo.de',
                ],
            ],
        ]);
        $testOrder = $this->createOrder($this->customerId, $this->email, $this->password);

        $this->getContainer()->get('order.repository')->update([
            [
                'id' => $testOrder,
                'salesChannelId' => $testChannel['id'],
            ],
        ], $this->context);

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray(new Criteria())
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertCount(1, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertEquals($this->orderId, $response['orders']['elements'][0]['id']);
        static::assertEquals(Defaults::SALES_CHANNEL, $response['orders']['elements'][0]['salesChannelId']);
    }

    protected function getValidPaymentMethods(): EntitySearchResult
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('availabilityRuleId', null))
            ->addFilter(new EqualsFilter('active', true));

        return $repository->search($criteria, $this->context);
    }

    private function createOrder(string $customerId, string $email, string $password): string
    {
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $customerId, $email, $password, $this->context);
        $this->orderRepository->create($orderData, $this->context);

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
                'orderNumber' => Uuid::randomHex(),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context)->getId(),
                'paymentMethodId' => $this->defaultPaymentMethodId,
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'transactions' => [
                    [
                        'id' => Uuid::randomHex(),
                        'paymentMethodId' => $this->defaultPaymentMethodId,
                        'amount' => [
                            'unitPrice' => 5.0,
                            'totalPrice' => 15.0,
                            'quantity' => 3,
                            'calculatedTaxes' => [],
                            'taxRules' => [],
                        ],
                        'stateId' => $this->stateMachineRegistry->getInitialState(
                            OrderTransactionStates::STATE_MACHINE,
                            $this->context
                        )->getId(),
                    ],
                ],
                'deliveries' => [
                    [
                        'stateId' => $this->stateMachineRegistry->getInitialState(OrderDeliveryStates::STATE_MACHINE, $context)->getId(),
                        'shippingMethodId' => $this->getValidShippingMethodId(),
                        'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingDateEarliest' => date(\DATE_ISO8601),
                        'shippingDateLatest' => date(\DATE_ISO8601),
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
                        'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
                        'good' => true,
                    ],
                ],
                'deepLinkCode' => Uuid::randomHex(),
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
                            'countryId' => $this->getValidCountryId(),
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

    private function createDocument(string $orderId, bool $showInCustomerAccount = true, bool $sent = true): void
    {
        $documentRepository = $this->getContainer()->get('document.repository');

        $documentTypeRepository = $this->getContainer()->get('document_type.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', DeliveryNoteGenerator::DELIVERY_NOTE));

        /** @var DocumentTypeEntity $documentType */
        $documentType = $documentTypeRepository->search($criteria, $this->context)->first();

        $documentRepository->create(
            [
                [
                    'id' => Uuid::randomHex(),
                    'documentTypeId' => $documentType->getId(),
                    'fileType' => FileTypes::PDF,
                    'orderId' => $orderId,
                    'config' => ['documentNumber' => '1001', 'displayInCustomerAccount' => $showInCustomerAccount],
                    'deepLinkCode' => 'test',
                    'sent' => $sent,
                    'static' => false,
                ],
            ],
            $this->context
        );
    }
}
