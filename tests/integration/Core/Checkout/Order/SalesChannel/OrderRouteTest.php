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

    private int $mailSentEventCounter = 0;

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

        $this->contextPersister = static::getContainer()->get(SalesChannelContextPersister::class);
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->requestCriteriaBuilder = static::getContainer()->get(RequestCriteriaBuilder::class);
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

        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
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
        static::assertSame($this->orderId, $response['orders']['elements'][0]['id']);
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
        static::assertSame($this->orderId, $response['orders']['elements'][0]['id']);
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
                '/store-api/order?checkPromotion=true',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($this->requestCriteriaBuilder->toArray($criteria), \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertSame($this->orderId, $response['orders']['elements'][0]['id']);
        static::assertIsArray($response);
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
        static::assertSame($this->defaultPaymentMethodId, $transaction->getPaymentMethodId());
    }

    public function testSetAnotherPaymentMethodToOrder(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $dispatcher = static::getContainer()->get('event_dispatcher');
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun): void {
            $eventDidRun = true;
            static::assertStringContainsString('The payment for your order with Storefront is cancelled', $event->getContents()['text/html']);
            static::assertStringContainsString('Message: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
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
        if (!static::getContainer()->has(AccountOrderController::class)) {
            static::markTestSkipped('Order mail tests should be fixed without storefront');
        }

        // Clear entity cache to ensure fresh data
        static::getContainer()->get('cache.object')->clear();

        // Ensure the order transaction is in initial state so the test behavior is predictable
        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('transactions');
        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();
        static::assertNotNull($order);
        $transactions = $order->getTransactions();
        static::assertNotNull($transactions);

        // Ensure we have exactly one transaction with the default payment method
        // This is critical for v6.8.0.0 OFF behavior where last() must match the payment method
        static::assertCount(1, $transactions, 'Order must have exactly one transaction for this test');

        $transaction = $transactions->last();
        static::assertNotNull($transaction);
        static::assertSame($this->defaultPaymentMethodId, $transaction->getPaymentMethodId(), 'Transaction must have the default payment method');

        $initialStateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE);
        if ($transaction->getStateId() !== $initialStateId) {
            // Reset transaction to initial state
            static::getContainer()->get('order_transaction.repository')->update([
                [
                    'id' => $transaction->getId(),
                    'stateId' => $initialStateId,
                ],
            ], Context::createDefaultContext());

            // Clear cache and reload to ensure state is updated
            static::getContainer()->get('cache.object')->clear();

            // Reload the order to ensure the updated state is seen by the subsequent request
            $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();
            static::assertNotNull($order);
            $transactions = $order->getTransactions();
            static::assertNotNull($transactions);
            $transaction = $transactions->last();
            static::assertNotNull($transaction);
            static::assertSame($initialStateId, $transaction->getStateId(), 'Transaction state must be initial after update');
        }

        $dispatcher = static::getContainer()->get('event_dispatcher');
        $this->mailSentEventCounter = 0;

        $this->addEventListener($dispatcher, MailSentEvent::class, $this->handleMailSentEvent(...));

        // Final verification: Ensure transaction is still in initial state right before the request
        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();
        static::assertNotNull($order);
        $verifyTransaction = $order->getTransactions()?->last();
        static::assertNotNull($verifyTransaction);
        static::assertSame($initialStateId, $verifyTransaction->getStateId(), 'Transaction state changed unexpectedly before API request');
        static::assertSame($this->defaultPaymentMethodId, $verifyTransaction->getPaymentMethodId(), 'Payment method changed unexpectedly before API request');

        // DEBUG: Output pre-request state
        echo "\n=== DEBUG: Pre-request state ===\n";
        echo "Feature v6.8.0.0 active: " . (Feature::isActive('v6.8.0.0') ? 'YES' : 'NO') . "\n";
        echo "Order ID: {$this->orderId}\n";
        echo "Transaction ID: {$verifyTransaction->getId()}\n";
        echo "Transaction State ID: {$verifyTransaction->getStateId()}\n";
        echo "Initial State ID: {$initialStateId}\n";
        echo "Payment Method ID: {$verifyTransaction->getPaymentMethodId()}\n";
        echo "Expected Payment Method: {$this->defaultPaymentMethodId}\n";
        echo "Transaction count: " . $order->getTransactions()->count() . "\n";

        // Compare primary vs last
        $primaryTransaction = $order->getPrimaryOrderTransaction();
        $lastTransaction = $order->getTransactions()->last();
        echo "Primary transaction ID: " . ($primaryTransaction ? $primaryTransaction->getId() : 'NULL') . "\n";
        echo "Last transaction ID: " . ($lastTransaction ? $lastTransaction->getId() : 'NULL') . "\n";
        echo "Primary == Last: " . (($primaryTransaction && $lastTransaction && $primaryTransaction->getId() === $lastTransaction->getId()) ? 'YES' : 'NO') . "\n";
        echo "================================\n";

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

        // DEBUG: Output post-request state
        $orderAfter = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();
        static::assertNotNull($orderAfter);
        $transactionsAfter = $orderAfter->getTransactions();
        echo "\n=== DEBUG: Post-request state ===\n";
        echo "Transaction count after: " . ($transactionsAfter ? $transactionsAfter->count() : 0) . "\n";
        echo "Mail sent counter: {$this->mailSentEventCounter}\n";
        echo "Expected counter: " . (Feature::isActive('v6.8.0.0') ? 1 : 0) . "\n";
        if ($transactionsAfter) {
            foreach ($transactionsAfter as $idx => $trans) {
                echo "Transaction {$idx}: ID={$trans->getId()}, PaymentMethod={$trans->getPaymentMethodId()}, State={$trans->getStateId()}\n";
            }
        }
        echo "=================================\n";

        $dispatcher->removeListener(MailSentEvent::class, $this->handleMailSentEvent(...));

        // see SetPaymentOrderRoute tryTransition()
        // primaryOrderTransactionId cannot be set via update(), so getPrimaryOrderTransaction() returns NULL
        // When v6.8.0.0 is OFF: uses last() → finds match → returns true → NO email (counter = 0)
        // When v6.8.0.0 is ON: uses getPrimaryOrderTransaction() → NULL → returns false → creates transaction → email sent (counter = 1)
        static::assertSame(Feature::isActive('v6.8.0.0') ? 1 : 0, $this->mailSentEventCounter, 'Mail sent counter does not match expected behavior based on feature flag');
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
        static::assertSame($this->orderId, $response['orders']['elements'][0]['id']);
        static::assertSame(TestDefaults::SALES_CHANNEL, $response['orders']['elements'][0]['salesChannelId']);
    }

    protected function getValidPaymentMethods(): PaymentMethodCollection
    {
        /** @var EntityRepository<PaymentMethodCollection> $paymentMethodRepository */
        $paymentMethodRepository = static::getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('availabilityRuleId', null))
            ->addFilter(new EqualsFilter('active', true));

        return $paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    protected function getValidCountries(): CountryCollection
    {
        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');

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
        $transactionId = Uuid::randomHex();

        $order = [
            [
                'id' => $orderId,
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'orderNumber' => Uuid::randomHex(),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE),
                'paymentMethodId' => $this->defaultPaymentMethodId,
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'primaryOrderTransactionId' => $transactionId,
                'transactions' => [
                    [
                        'id' => $transactionId,
                        'paymentMethodId' => $this->defaultPaymentMethodId,
                        'amount' => [
                            'unitPrice' => 5.0,
                            'totalPrice' => 15.0,
                            'quantity' => 3,
                            'calculatedTaxes' => [],
                            'taxRules' => [],
                        ],
                        'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
                    ],
                ],
                'deliveries' => [
                    [
                        'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
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

        $document = static::getContainer()->get(DocumentGenerator::class)
            ->generate(DeliveryNoteRenderer::TYPE, [$orderId => $operation], Context::createDefaultContext())->getSuccess()->first();

        static::assertNotNull($document);

        static::getContainer()->get('document.repository')->update([
            [
                'id' => $document->getId(),
                'sent' => $sent,
            ],
        ], Context::createDefaultContext());
    }

    private function handleMailSentEvent(MailSentEvent $event): void
    {
        ++$this->mailSentEventCounter;
        static::assertStringContainsString('The payment for your order with Storefront is cancelled', $event->getContents()['text/html']);
        static::assertStringContainsString('Message: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
    }
}
