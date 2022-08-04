<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Rule\BillingCountryRule;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\AccountOrderController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 * @group slow
 * @group store-api
 */
class OrderRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use MailTemplateTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use PromotionIntegrationTestBehaviour;

    private KernelBrowser $browser;

    private EntityRepositoryInterface $orderRepository;

    private string $orderId;

    private SalesChannelContextPersister $contextPersister;

    private RequestCriteriaBuilder $requestCriteriaBuilder;

    private string $customerId;

    private string $email;

    private string $password;

    private string $defaultPaymentMethodId;

    private string $defaultCountryId;

    private string $deepLinkCode;

    private EntityRepositoryInterface $customerRepository;

    protected function setUp(): void
    {
        $this->defaultCountryId = $this->getValidCountryId(null);
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => TestDefaults::SALES_CHANNEL,
            'languages' => [],
            'countryId' => $this->defaultCountryId,
            'countries' => \array_map(static function (CountryEntity $country) {
                return ['id' => $country->getId()];
            }, $this->getValidCountries()->getEntities()->getElements()),
        ]);

        $this->assignSalesChannelContext($this->browser);

        $this->contextPersister = $this->getContainer()->get(SalesChannelContextPersister::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
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
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'email' => $this->email,
                    'password' => $this->password,
                ]) ?: ''
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        /** @var AbstractSalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create($response['contextToken'], TestDefaults::SALES_CHANNEL);

        $newToken = $this->contextPersister->replace($response['contextToken'], $salesChannelContext);
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

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

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
        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->get($this->orderId);

        static::assertNotNull($order);
        static::assertNotNull($order->getOrderCustomer());

        $this->customerRepository->update([[
            'id' => $order->getOrderCustomer()->getCustomerId(),
            'guest' => true,
        ]], Context::createDefaultContext());

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

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

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
        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->get($this->orderId);

        static::assertNotNull($order);
        static::assertNotNull($order->getOrderCustomer());

        $this->customerRepository->update([[
            'id' => $order->getOrderCustomer()->getCustomerId(),
            'guest' => true,
        ]], Context::createDefaultContext());

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

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

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

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

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

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

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
                )) ?: ''
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

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
                ]) ?: ''
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('transactions');

        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->get($this->orderId);

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

        /** @var EventDispatcher $dispatcher */
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
        $newPaymentMethodId = $this->getValidPaymentMethods()->filter(function (PaymentMethodEntity $paymentMethod) use ($defaultPaymentMethodId) {
            return $paymentMethod->getId() !== $defaultPaymentMethodId;
        })->first()->getId();

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
                ]) ?: ''
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testUpdatedRulesOnPaymentMethodChange(): void
    {
        Feature::skipTestIfActive('FEATURE_NEXT_17858', $this);

        if (!$this->getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $defaultPaymentMethodId = $this->defaultPaymentMethodId;
        $this->getContainer()->get('customer.repository')->update([
            [
                'id' => $this->customerId,
                'defaultPaymentMethod' => [
                    'id' => $defaultPaymentMethodId,
                ],
            ],
        ], Context::createDefaultContext());

        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $context = $salesChannelContext->getContext();

        // prepare rules and conditions for payment methods

        $newPaymentRule = Uuid::randomHex();
        $defaultPaymentRule = Uuid::randomHex();
        foreach ([$newPaymentRule, $defaultPaymentRule] as $ruleId) {
            $this->getContainer()->get('rule.repository')->create(
                [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1, 'moduleTypes' => ['types' => ['payment']]]],
                $context
            );
        }

        $newPaymentMethod = $this->getValidPaymentMethods()->filter(function (PaymentMethodEntity $paymentMethod) use ($defaultPaymentMethodId) {
            return $paymentMethod->getId() !== $defaultPaymentMethodId;
        })->first();
        static::assertNotNull($newPaymentMethod);
        $newPaymentMethodId = $newPaymentMethod->getId();

        foreach ([$newPaymentRule => $newPaymentMethodId, $defaultPaymentRule => $defaultPaymentMethodId] as $ruleId => $paymentId) {
            $this->getContainer()->get('rule_condition.repository')->create(
                [
                    [
                        'id' => Uuid::randomHex(),
                        'type' => (new PaymentMethodRule())->getName(),
                        'ruleId' => $ruleId,
                        'value' => [
                            'operator' => Rule::OPERATOR_EQ,
                            'paymentMethodIds' => [$paymentId],
                        ],
                    ],
                ],
                $context
            );
        }

        // create promotion + discount with rule for default payment method

        $promotionId = Uuid::randomHex();
        $this->createPromotionWithCustomData([
            'id' => $promotionId,
            'name' => 'Test Promotion',
            'active' => true,
            'salesChannels' => [
                ['salesChannelId' => $salesChannelContext->getSalesChannelId(), 'priority' => 1],
            ],
            'cartRules' => [
                ['id' => $defaultPaymentRule],
            ],
        ], $this->getContainer()->get('promotion.repository'), $salesChannelContext);
        $this->createTestFixtureDiscount(
            $promotionId,
            PromotionDiscountEntity::TYPE_PERCENTAGE,
            PromotionDiscountEntity::SCOPE_CART,
            20,
            null,
            $this->getContainer(),
            $salesChannelContext
        );

        // create the order

        $cart = $this->getContainer()->get(CartService::class)->createNew($salesChannelContext->getToken());
        $cart = $this->addProduct($this->createProduct(), 1, $cart, $this->getContainer()->get(CartService::class), $salesChannelContext);

        $orderId = $this->getContainer()->get(CartService::class)->order($cart, $salesChannelContext, new RequestDataBag());

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');

        // test the new order has a promotion line item

        $newlyCreatedOrder = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->get($orderId);

        static::assertNotNull($newlyCreatedOrder);
        static::assertNotNull($newlyCreatedOrder->getLineItems());
        static::assertEquals(1, $newlyCreatedOrder->getLineItems()->filterByType('promotion')->count());

        // create a business event for order transaction state change to in progress with rule for new payment method

        $mailTemplate = $this->getContainer()
            ->get('mail_template.repository')
            ->search((new Criteria())->addAssociation('mailTemplateType')
                ->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'order.state.in_progress')), $context)
            ->first();

        static::assertNotNull($mailTemplate);
        $this->getContainer()->get('event_action.repository')->create([[
            'eventName' => 'state_enter.order_transaction.state.in_progress',
            'actionName' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
            'config' => [
                'recipients' => ['admin@test.test' => 'admin'],
                'mail_template_id' => $mailTemplate->getId(),
                'mail_template_type_id' => $mailTemplate->getMailTemplateTypeId(),
            ],
            'rules' => [
                ['id' => $newPaymentRule],
            ],
            'active' => true,
        ]], $context);

        // change payment method from default payment method to new payment method

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $orderId,
                    'paymentMethodId' => $newPaymentMethodId,
                ]
            );

        // change the order transaction state to in progress
        // check that mail event was dispatched by business event based on rule for new payment

        $transaction = $this->getContainer()->get('order_transaction.repository')
            ->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('orderId', $orderId))
                    ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING)),
                $context
            )->first();
        static::assertNotNull($transaction);

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDidRun = false;
        $recipients = [];
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, &$recipients): void {
            $eventDidRun = true;
            $recipients = $event->getRecipients();
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->getContainer()->get(OrderTransactionStateHandler::class)->process($transaction->getId(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
        static::assertArrayHasKey('admin@test.test', $recipients);

        // test that order still hase promotion line item

        $newlyCreatedOrder = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();
        static::assertNotNull($newlyCreatedOrder);
        static::assertEquals(1, $newlyCreatedOrder->getLineItems()->filterByType('promotion')->count());

        // add product to order and recalculate and test that recalculated order still has original promotion line item

        $versionId = $this->getContainer()->get(DefinitionInstanceRegistry::class)
            ->getRepository('order')->createVersion($orderId, $context, Uuid::randomHex(), Uuid::randomHex());
        $versionContext = $context->createWithVersionId($versionId);

        $this->getContainer()->get(RecalculationService::class)->addProductToOrder($orderId, $this->createProduct(), 1, $versionContext);
        $this->getContainer()->get(RecalculationService::class)->recalculateOrder($orderId, $versionContext);

        $newlyCreatedOrder = $this->orderRepository->search($criteria, $versionContext)->first();
        static::assertNotNull($newlyCreatedOrder);
        static::assertNotNull($newlyCreatedOrder->getLineItems());
        static::assertEquals(1, $newlyCreatedOrder->getLineItems()->filterByType('promotion')->count());
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
                ]) ?: ''
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

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
                ]) ?: ''
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

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
                ]) ?: ''
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

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
        $orderData = $this->getOrderData($orderId, $this->customerId, $this->email, $this->password, Context::createDefaultContext());
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

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
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
        $countryId = $this->getValidCountries()->filter(function (CountryEntity $country) {
            return $country->getId() !== $this->defaultCountryId;
        })->first()->getId();

        // create rule for that country now, so it is set in the order
        $ruleId = Uuid::randomHex();
        $this->getContainer()->get('rule.repository')->create([[
            'id' => $ruleId,
            'name' => 'test',
            'priority' => 1,
            'conditions' => [[
                'type' => (new BillingCountryRule())->getName(),
                'value' => [
                    'operator' => '=',
                    'countryIds' => [$countryId],
                ],
            ]],
        ]], Context::createDefaultContext());

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

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertCount(0, $response['errors']);

        $this->browser->request(
            'POST',
            '/store-api/checkout/order',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode([]) ?: ''
        );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertArrayNotHasKey('errors', $response);

        $orderId = $response['id'];

        // change customer country, so rule is valid
        $this->getContainer()->get('customer.repository')->update([[
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
        ]], Context::createDefaultContext());
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
            ]) ?: ''
        );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true);

        static::assertArrayHasKey('errors', $response);
        static::assertEquals('CHECKOUT__UNKNOWN_PAYMENT_METHOD', $response['errors'][0]['code']);
    }

    protected function getValidPaymentMethods(): EntitySearchResult
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('availabilityRuleId', null))
            ->addFilter(new EqualsFilter('active', true));

        return $repository->search($criteria, Context::createDefaultContext());
    }

    protected function getValidCountries(): EntitySearchResult
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('country.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('taxFree', 0))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true));

        return $repository->search($criteria, Context::createDefaultContext());
    }

    private function createOrder(string $customerId, string $email, string $password): string
    {
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $customerId, $email, $password, Context::createDefaultContext());
        $this->orderRepository->create($orderData, Context::createDefaultContext());

        return $orderId;
    }

    private function getOrderData(string $orderId, string $customerId, string $email, string $password, Context $context): array
    {
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        return [
            [
                'id' => $orderId,
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
                                    'id' => TestDefaults::SALES_CHANNEL,
                                ],
                            ],
                        ],
                        'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
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
    }

    private function createDocument(string $orderId, bool $showInCustomerAccount = true, bool $sent = true): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', DeliveryNoteRenderer::TYPE));

        $documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
        $documentRepository = $this->getContainer()->get('document.repository');

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            ['documentNumber' => '1001', 'displayInCustomerAccount' => $showInCustomerAccount],
        );

        $doccument = $documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$orderId => $operation], Context::createDefaultContext())->getSuccess()->first();

        static::assertNotNull($doccument);

        $documentRepository->update([
            [
                'id' => $doccument->getId(),
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

    private function createProduct(): string
    {
        $productId = Uuid::randomHex();

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 19.99, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => $productId, 'name' => 'shopware AG'],
            'tax' => ['id' => $this->getValidTaxId(), 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $productId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $productId,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        return $productId;
    }

    private function createDefaultSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $this->customerId]);
    }
}
