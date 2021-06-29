<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
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
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
    use PromotionTestFixtureBehaviour;
    use PromotionIntegrationTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

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
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'email' => $this->email,
                    'password' => $this->password,
                ])
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
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $this->defaultPaymentMethodId,
                ])
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
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $newPaymentMethodId,
                ])
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testUpdatedRulesOnPaymentMethodChange(): void
    {
        $defaultPaymentMethodId = $this->defaultPaymentMethodId;
        $this->getContainer()->get('customer.repository')->update([
            [
                'id' => $this->customerId,
                'defaultPaymentMethod' => [
                    'id' => $defaultPaymentMethodId,
                ],
            ],
        ], $this->context);

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

        $newPaymentMethodId = $this->getValidPaymentMethods()->filter(function (PaymentMethodEntity $paymentMethod) use ($defaultPaymentMethodId) {
            return $paymentMethod->getId() !== $defaultPaymentMethodId;
        })->first()->getId();

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

        /** @var OrderEntity $newlyCreatedOrder */
        $newlyCreatedOrder = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertEquals(1, $newlyCreatedOrder->getLineItems()->filterByType('promotion')->count());

        // create a business event for order transaction state change to in progress with rule for new payment method

        /** @var MailTemplateEntity $mailTemplate */
        $mailTemplate = $this->getContainer()
            ->get('mail_template.repository')
            ->search((new Criteria())->addAssociation('mailTemplateType')
                ->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'order.state.in_progress')), $context)
            ->first();

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

        /** @var OrderTransactionEntity $transaction */
        $transaction = $this->getContainer()->get('order_transaction.repository')
            ->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('orderId', $orderId))
                    ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING)),
                $context
            )->first();

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDidRun = false;
        $recipients = [];
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, &$recipients): void {
            $eventDidRun = true;
            $recipients = $event->getRecipients();
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $this->getContainer()->get(OrderTransactionStateHandler::class)->process($transaction->getId(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
        static::assertArrayHasKey('admin@test.test', $recipients);

        // test that order still hase promotion line item

        /** @var OrderEntity $newlyCreatedOrder */
        $newlyCreatedOrder = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertEquals(1, $newlyCreatedOrder->getLineItems()->filterByType('promotion')->count());

        // add product to order and recalculate and test that recalculated order still has original promotion line item

        $versionId = $this->getContainer()->get(DefinitionInstanceRegistry::class)
            ->getRepository('order')->createVersion($orderId, $context, Uuid::randomHex(), Uuid::randomHex());
        $versionContext = $context->createWithVersionId($versionId);

        $this->getContainer()->get(RecalculationService::class)->addProductToOrder($orderId, $this->createProduct(), 1, $versionContext);
        $this->getContainer()->get(RecalculationService::class)->recalculateOrder($orderId, $versionContext);

        /** @var OrderEntity $newlyCreatedOrder */
        $newlyCreatedOrder = $this->orderRepository->search($criteria, $versionContext)->first();

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

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

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
                ])
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
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => Uuid::randomHex(),
                ])
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
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                ])
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

        $this->orderRepository->update([
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

    public function testPaymentOrderNotManipulable(): void
    {
        $ruleRepository = $this->getContainer()->get('rule.repository');

        // Get customer from USA rule
        $ruleCriteria = new Criteria();
        $ruleCriteria->addFilter(new EqualsFilter('name', 'Customers from USA'));

        $ruleId = $ruleRepository->search($ruleCriteria, $this->context)->first()->getId();

        $paymentId = $this->createCustomPaymentWithRule($ruleId);

        $ids = new IdsCollection();

        $this->getContainer()->get('product.repository')->create(
            [
                (new ProductBuilder($ids, '1000'))
                    ->price(10)
                    ->name('Test product')
                    ->active(true)
                    ->visibility()
                    ->build(),
            ],
            $ids->getContext()
        );

        $this->browser
            ->request(
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
                ])
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertCount(0, $response['errors']);

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([])
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayNotHasKey('errors', $response);

        $orderId = $response['id'];

        // Get USA country id
        $countryCriteria = new Criteria();
        $countryCriteria->addFilter(new EqualsFilter('iso3', 'USA'));

        $countryId = $this->getContainer()->get('country.repository')->search($countryCriteria, $this->context)->first()->getId();

        // Set customer country to USA
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
        ], $this->context);

        // Request payment change
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $orderId,
                    'paymentMethodId' => $paymentId,
                ])
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        return [
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
                        'id' => Defaults::SALES_CHANNEL,
                    ],
                ],
            ],
        ], $this->context);

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
                    'salesChannelId' => Defaults::SALES_CHANNEL,
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

        return $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $this->customerId]);
    }
}
