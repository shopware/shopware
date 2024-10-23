<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\Rule\BillingCountryRule;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\AccountOrderController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('slow')]
#[Group('store-api')]
class OrderRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private string $orderId;

    private SalesChannelContextPersister $contextPersister;

    private RequestCriteriaBuilder $requestCriteriaBuilder;

    private string $customerId;

    private string $email;

    private string $defaultPaymentMethodId;

    private string $defaultCountryId;

    private string $deepLinkCode;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->defaultCountryId = $this->getValidCountryId(null);

        $validCountries = $this->getValidCountries()->getElements();
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => TestDefaults::SALES_CHANNEL,
            'languages' => [],
            'countryId' => $this->defaultCountryId,
            'countries' => \array_map(static fn (CountryEntity $country) => ['id' => $country->getId()], $validCountries),
        ]);

        $this->assignSalesChannelContext($this->browser);

        $this->contextPersister = $this->getContainer()->get(SalesChannelContextPersister::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->requestCriteriaBuilder = $this->getContainer()->get(RequestCriteriaBuilder::class);
        $this->email = Uuid::randomHex() . '@example.com';
        $this->customerId = Uuid::randomHex();
        $firstPaymentMethod = $this->getValidPaymentMethods()->first();
        static::assertNotNull($firstPaymentMethod);
        $this->defaultPaymentMethodId = $firstPaymentMethod->getId();
        $this->orderId = $this->createOrder($this->customerId, $this->email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'email' => $this->email,
                    'password' => 'shopware',
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create($contextToken, TestDefaults::SALES_CHANNEL);

        $newToken = $this->contextPersister->replace($contextToken, $salesChannelContext);
        $this->contextPersister->save(
            $newToken,
            [
                'customerId' => $this->customerId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            TestDefaults::SALES_CHANNEL,
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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertEquals($this->orderId, $response['orders']['elements'][0]['id']);
    }

    public function testGetOrderGuest(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', '');

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('orderCustomer');

        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($this->orderId);

        static::assertNotNull($order);
        static::assertNotNull($order->getOrderCustomer());

        $this->customerRepository->update([
            [
                'id' => $order->getOrderCustomer()->getCustomerId(),
                'guest' => true,
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$this->orderId]);
        $criteria->addFilter(new EqualsFilter('deepLinkCode', $this->deepLinkCode));

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                \array_merge(
                    $this->requestCriteriaBuilder->toArray($criteria),
                    [
                        'email' => 'test@example.com',
                        'zipcode' => '59438-0403',
                    ]
                )
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertEquals($this->orderId, $response['orders']['elements'][0]['id']);
    }

    public function testGetOrderGuestWrongDeepLink(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', '');

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('orderCustomer');

        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($this->orderId);

        static::assertNotNull($order);
        static::assertNotNull($order->getOrderCustomer());

        $this->customerRepository->update([
            [
                'id' => $order->getOrderCustomer()->getCustomerId(),
                'guest' => true,
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$this->orderId]);
        $criteria->addFilter(new EqualsFilter('deepLinkCode', Uuid::randomHex()));

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                \array_merge(
                    $this->requestCriteriaBuilder->toArray($criteria),
                    [
                        'email' => 'test@example.com',
                        'zipcode' => '59438-0403',
                    ]
                )
            );

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    public function testGetOrderGuestNoOrder(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', '');

        $criteria = new Criteria([Uuid::randomHex()]);
        $criteria->addFilter(new EqualsFilter('deepLinkCode', $this->deepLinkCode));

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria),
            );

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
                json_encode(
                    array_merge(
                        $this->requestCriteriaBuilder->toArray($criteria),
                        ['checkPromotion' => true]
                    ),
                    \JSON_THROW_ON_ERROR
                ) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $this->defaultPaymentMethodId,
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('transactions');

        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($this->orderId);

        static::assertNotNull($order);
        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->last());
        static::assertEquals($this->defaultPaymentMethodId, $transaction->getPaymentMethodId());
    }

    public function testSetAnotherPaymentMethodToOrder(): void
    {
        if (!$this->getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('The payment for your order with Storefront is cancelled', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('Message: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $defaultPaymentMethodId = $this->defaultPaymentMethodId;
        $newPaymentMethod = $this->getValidPaymentMethods()->filter(fn (PaymentMethodEntity $paymentMethod) => $paymentMethod->getId() !== $defaultPaymentMethodId)->first();
        $newPaymentMethodId = $newPaymentMethod?->getId() ?? '';

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $newPaymentMethodId,
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testSetSamePaymentMethodToOrder(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('The payment for your order with Storefront is cancelled', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('Message: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $this->defaultPaymentMethodId,
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => Uuid::randomHex(),
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testCancelOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/state/cancel',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->customerId, $this->email);
        unset($orderData[0]['orderCustomer']['customer']['password']);
        $this->orderRepository->create($orderData, Context::createDefaultContext());

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'salesChannelId' => $testChannel['id'],
            ],
        ], Context::createDefaultContext());

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray(new Criteria())
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertIsArray($response['orders']['elements']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertCount(1, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertEquals($this->orderId, $response['orders']['elements'][0]['id']);
        static::assertEquals(TestDefaults::SALES_CHANNEL, $response['orders']['elements'][0]['salesChannelId']);
    }

    public function testPaymentOrderNotManipulable(): void
    {
        $ids = new IdsCollection();

        // get non default country id
        $country = $this->getValidCountries()->filter(fn (CountryEntity $country) => $country->getId() !== $this->defaultCountryId)->first();
        $countryId = $country?->getId() ?? '';

        // create rule for that country now, so it is set in the order
        $ruleId = Uuid::randomHex();
        $this->getContainer()->get('rule.repository')->create([
            [
                'id' => $ruleId,
                'name' => 'test',
                'priority' => 1,
                'conditions' => [
                    [
                        'type' => (new BillingCountryRule())->getName(),
                        'value' => [
                            'operator' => '=',
                            'countryIds' => [$countryId],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->getContainer()->get('product.repository')->create([
            (new ProductBuilder($ids, '1000'))
                ->price(10)
                ->name('Test product')
                ->active(true)
                ->visibility()
                ->build(),
        ], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'items' => [
                    [
                        'id' => $ids->get('1000'),
                        'referencedId' => $ids->get('1000'),
                        'quantity' => 1,
                        'type' => 'product',
                    ],
                ],
            ]) ?: ''
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(0, $response['errors']);

        $this->browser->request(
            'POST',
            '/store-api/checkout/order',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode([]) ?: ''
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);

        $orderId = $response['id'];

        // change customer country, so rule is valid
        $this->getContainer()->get('customer.repository')->update([
            [
                'id' => $this->customerId,
                'defaultBillingAddress' => [
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $countryId,
                ],
            ],
        ], Context::createDefaultContext());
        $paymentId = $this->createCustomPaymentWithRule($ruleId);

        // Request payment change
        $this->browser->request(
            'POST',
            '/store-api/order/payment',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode([
                'orderId' => $orderId,
                'paymentMethodId' => $paymentId,
            ], \JSON_THROW_ON_ERROR) ?: ''
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertEquals('CHECKOUT__ORDER_PAYMENT_METHOD_NOT_AVAILABLE', $response['errors'][0]['code']);
    }

    protected function getValidPaymentMethods(): PaymentMethodCollection
    {
        /** @var EntityRepository<PaymentMethodCollection> $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('availabilityRuleId', null))
            ->addFilter(new EqualsFilter('active', true));

        return $paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    protected function getValidCountries(): CountryCollection
    {
        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = $this->getContainer()->get('country.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true));

        return $countryRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    private function createOrder(string $customerId, string $email): string
    {
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $customerId, $email);
        $this->orderRepository->create($orderData, Context::createDefaultContext());

        return $orderId;
    }

    /**
     * @return array<mixed>
     */
    private function getOrderData(string $orderId, string $customerId, string $email): array
    {
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        $order = [
            [
                'id' => $orderId,
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'orderNumber' => Uuid::randomHex(),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE),
                'paymentMethodId' => $this->defaultPaymentMethodId,
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
                        'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
                    ],
                ],
                'deliveries' => [
                    [
                        'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                        'shippingMethodId' => $this->getValidShippingMethodId(),
                        'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingDateEarliest' => date(\DATE_ATOM),
                        'shippingDateLatest' => date(\DATE_ATOM),
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
                'deepLinkCode' => $this->deepLinkCode = Uuid::randomHex(),
                'orderCustomer' => [
                    'email' => 'test@example.com',
                    'firstName' => 'Noe',
                    'lastName' => 'Hill',
                    'salutationId' => $salutation,
                    'title' => 'Doc',
                    'customerNumber' => 'Test',
                    'customer' => [
                        'id' => $customerId,
                        'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
                        'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                        'email' => $email,
                        'password' => TestDefaults::HASHED_PASSWORD,
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

        if (!Feature::isActive('v6.7.0.0')) {
            $order[0]['orderCustomer']['customer']['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        return $order;
    }

    private function createDocument(string $orderId, bool $showInCustomerAccount = true, bool $sent = true): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', DeliveryNoteRenderer::TYPE));

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            ['documentNumber' => '1001', 'displayInCustomerAccount' => $showInCustomerAccount],
        );

        $document = $this->getContainer()->get(DocumentGenerator::class)
            ->generate(DeliveryNoteRenderer::TYPE, [$orderId => $operation], Context::createDefaultContext())->getSuccess()->first();

        static::assertNotNull($document);

        $this->getContainer()->get('document.repository')->update([
            [
                'id' => $document->getId(),
                'sent' => $sent,
            ],
        ], Context::createDefaultContext());
    }

    private function createCustomPaymentWithRule(string $ruleId): string
    {
        $paymentId = Uuid::randomHex();

        $this->getContainer()->get('payment_method.repository')->create([
            [
                'id' => $paymentId,
                'name' => 'Test Payment with Rule',
                'technicalName' => 'payment_test_rule',
                'description' => 'Payment rule test',
                'active' => true,
                'afterOrderEnabled' => true,
                'availabilityRuleId' => $ruleId,
                'salesChannels' => [
                    [
                        'id' => TestDefaults::SALES_CHANNEL,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $paymentId;
    }
}
