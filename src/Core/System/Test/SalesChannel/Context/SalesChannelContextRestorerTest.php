<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Checkout\Test\Customer\CustomerBuilder;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestorerOrderCriteriaEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class SalesChannelContextRestorerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SalesChannelContextRestorer $contextRestorer;

    private CartService $cartService;

    private array $events;

    private \Closure $callbackFn;

    private EventDispatcherInterface $eventDispatcher;

    private SalesChannelContextPersister $contextPersister;

    private string $customerId;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->cartService = $this->getContainer()->get(CartService::class);

        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $this->events = [];

        $this->callbackFn = function (Event $event): void {
            $this->events[\get_class($event)] = $event;
        };

        $this->contextPersister = $this->getContainer()->get(SalesChannelContextPersister::class);
        /** @var AbstractSalesChannelContextFactory $contextFactory */
        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $cartRuleLoader = $this->getContainer()->get(CartRuleLoader::class);

        $this->customerId = $this->createCustomer()->getId();

        $this->contextRestorer = new SalesChannelContextRestorer(
            $contextFactory,
            $cartRuleLoader,
            $this->getContainer()->get(OrderConverter::class),
            $this->getContainer()->get('order.repository'),
            $this->connection,
            $this->getContainer()->get(CartRestorer::class),
            $this->eventDispatcher
        );
    }

    public function testRestore(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $expectedToken = Uuid::randomHex();
        $expectedContext = $this->createSalesChannelContext($expectedToken, []);

        $currentContext = $this->createSalesChannelContext('currentToken', [], $this->customerId);

        $this->contextPersister->save($expectedContext->getToken(), [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $this->eventDispatcher->addListener(SalesChannelContextRestoredEvent::class, $this->callbackFn);

        $restoredContext = $this->contextRestorer->restore($this->customerId, $currentContext);

        static::assertSame($expectedContext->getToken(), $restoredContext->getToken());

        static::assertArrayHasKey(SalesChannelContextRestoredEvent::class, $this->events);
        $salesChannelRestoredEvent = $this->events[SalesChannelContextRestoredEvent::class];
        static::assertInstanceOf(SalesChannelContextRestoredEvent::class, $salesChannelRestoredEvent);
    }

    public function testRestoreByOrder(): void
    {
        $context = Context::createDefaultContext();
        $ids = new TestDataCollection();
        $this->createOrder($ids);
        $ruleId = Uuid::randomHex();
        $rule = [
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        // Create rule after create order
        $this->getContainer()->get('rule.repository')
            ->create([$rule], $context);

        $saleChanelContext = $this->contextRestorer->restoreByOrder($ids->create('order'), $context);
        static::assertTrue(\in_array($ruleId, $saleChanelContext->getRuleIds(), true));
    }

    public function testRestoreByCustomer(): void
    {
        $context = Context::createDefaultContext();
        $ids = new TestDataCollection();
        $this->createOrder($ids);
        $ruleId = Uuid::randomHex();
        $rule = [
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        // Create rule after create order
        $this->getContainer()->get('rule.repository')
            ->create([$rule], $context);

        $saleChanelContext = $this->contextRestorer->restoreByCustomer($this->createCustomer()->getId(), $context);
        static::assertTrue(\in_array($ruleId, $saleChanelContext->getRuleIds(), true));
    }

    public function testRestoreByCustomerPassesStates(): void
    {
        $context = Context::createDefaultContext();
        $context->addState('foo');

        $ids = new TestDataCollection();
        $this->createOrder($ids);

        $saleChanelContext = $this->contextRestorer->restoreByCustomer($this->createCustomer()->getId(), $context);
        static::assertTrue($saleChanelContext->getContext()->hasState('foo'));
    }

    public function testGuestContextAndCartAreDeleted(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $currentContextToken = Random::getAlphanumericString(32);

        $currentContext = $this->createSalesChannelContext($currentContextToken, []);

        $this->contextPersister->save($currentContextToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $cart = new Cart('test', $currentContextToken);

        $cart->add(new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE));
        $this->getContainer()->get(CartPersister::class)->save($cart, $currentContext);

        static::assertTrue($this->cartExists($currentContextToken));
        static::assertTrue($this->contextExists($currentContextToken));

        $newContext = $this->contextRestorer->restore($this->customerId, $currentContext);

        static::assertTrue($this->cartExists($newContext->getToken()));
        static::assertTrue($this->contextExists($newContext->getToken()));

        static::assertFalse($this->cartExists($currentContextToken));
        static::assertFalse($this->contextExists($currentContextToken));
    }

    public function testCartIsRecalculated(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $customerContextToken = Random::getAlphanumericString(32);

        $customerContext = $this->createSalesChannelContext($customerContextToken, []);

        $this->contextPersister->save($customerContextToken, [], $customerContext->getSalesChannel()->getId(), $this->customerId);

        $cart = new Cart('test', $customerContextToken);

        $productId = $this->createProduct($customerContext->getContext());

        $productLineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);
        $productLineItem->setStackable(true);
        $productLineItem->setQuantity(1);

        $cart->add($productLineItem);
        $cart->markUnmodified();

        static::assertCount(1, $cart->getLineItems());
        $this->getContainer()->get(CartPersister::class)->save($cart, $customerContext);

        $this->getContainer()->get('product.repository')->delete([[
            'id' => $productId,
        ]], $customerContext->getContext());

        $guestContext = $this->createSalesChannelContext('123123', []);

        $restoreContext = $this->contextRestorer->restore($this->customerId, $guestContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        // Delete product will removed from cart as result from recalculation
        static::assertCount(0, $restoreCart->getLineItems());
    }

    public function testCartIsMergedAndRecalculatedWithTheSavedOne(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $currentContextToken = Random::getAlphanumericString(32);

        $currentContext = $this->createSalesChannelContext($currentContextToken, []);

        // Create Guest cart
        $cart = new Cart('guest-cart', $currentContextToken);

        $productId1 = $this->createProduct($currentContext->getContext());
        $productId2 = $this->createProduct($currentContext->getContext());

        $productLineItem1 = new LineItem($productId1, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId1);
        $productLineItem2 = new LineItem($productId2, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId2);
        $productLineItem1->setStackable(true);
        $productLineItem2->setStackable(true);
        $productLineItem1->setQuantity(1);
        $guestProductQuantity = 5;
        $productLineItem2->setQuantity($guestProductQuantity);

        $cart->addLineItems(new LineItemCollection([$productLineItem1, $productLineItem2]));
        $cart->markUnmodified();

        $this->getContainer()->get(CartPersister::class)->save($cart, $currentContext);

        // Create Saved Customer cart
        $customerToken = Random::getAlphanumericString(32);
        $customerContext = $this->createSalesChannelContext($customerToken, []);

        $this->contextPersister->save($customerToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $cart = new Cart('customer-cart', $customerToken);

        $savedLineItem = new LineItem($productId2, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId2);
        $savedLineItemQuantity = 4;
        $savedLineItem->setStackable(true);
        $savedLineItem->setQuantity($savedLineItemQuantity);

        $productId3 = $this->createProduct($customerContext->getContext());
        $productLineItem3 = new LineItem($productId3, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId3);
        $productLineItem3->setStackable(true);
        $productLineItem3->setQuantity(3);

        $cart->addLineItems(new LineItemCollection([$savedLineItem, $productLineItem3]));
        $cart->markUnmodified();

        $this->getContainer()->get(CartPersister::class)->save($cart, $customerContext);

        // Delete 1 saved item
        $this->getContainer()->get('product.repository')->delete([[
            'id' => $productId3,
        ]], $customerContext->getContext());

        $this->eventDispatcher->addListener(CartMergedEvent::class, $this->callbackFn);

        $restoreContext = $this->contextRestorer->restore($this->customerId, $currentContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        // Delete product will removed from cart as result from recalculation
        static::assertEmpty($restoreCart->getLineItems()->get($productId3));

        static::assertArrayHasKey(CartMergedEvent::class, $this->events);
        $cartMergedEvent = $this->events[CartMergedEvent::class];
        static::assertInstanceOf(CartMergedEvent::class, $cartMergedEvent);

        static::assertEquals(1, $cartMergedEvent->getPreviousCart()->getLineItems()->count());
        static::assertEquals($cartMergedEvent->getCart()->getName(), $cartMergedEvent->getPreviousCart()->getName());
        static::assertEquals($cartMergedEvent->getCart()->getToken(), $cartMergedEvent->getPreviousCart()->getToken());

        static::assertNotEmpty($p1 = $restoreCart->getLineItems()->get($productId1));
        static::assertEquals(1, $p1->getQuantity());
        static::assertNotEmpty($savedItem = $restoreCart->getLineItems()->get($savedLineItem->getId()));
        static::assertEquals($savedLineItemQuantity + $guestProductQuantity, $savedItem->getQuantity());
    }

    public function testCartMergedEventIsFiredWithCustomerCart(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_16824', $this);
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $currentContextToken = Random::getAlphanumericString(32);

        $currentContext = $this->createSalesChannelContext($currentContextToken, []);

        // Create Guest cart
        $cart = new Cart('guest-cart', $currentContextToken);

        $productId1 = $this->createProduct($currentContext->getContext());
        $productId2 = $this->createProduct($currentContext->getContext());

        $productLineItem1 = new LineItem($productId1, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId1);
        $productLineItem2 = new LineItem($productId2, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId2);
        $productLineItem1->setStackable(true);
        $productLineItem2->setStackable(true);
        $productLineItem1->setQuantity(1);
        $guestProductQuantity = 5;
        $productLineItem2->setQuantity($guestProductQuantity);

        $cart->addLineItems(new LineItemCollection([$productLineItem1, $productLineItem2]));
        $cart->markUnmodified();

        $this->getContainer()->get(CartPersister::class)->save($cart, $currentContext);

        // Create Saved Customer cart
        $customerToken = Random::getAlphanumericString(32);
        $customerContext = $this->createSalesChannelContext($customerToken, []);

        $this->contextPersister->save($customerToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $cart = new Cart('customer-cart', $customerToken);

        $this->getContainer()->get(CartPersister::class)->save($cart, $customerContext);

        $this->eventDispatcher->addListener(CartMergedEvent::class, $this->callbackFn);

        $restoreContext = $this->contextRestorer->restore($this->customerId, $currentContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        static::assertArrayHasKey(CartMergedEvent::class, $this->events);

        /** @var CartMergedEvent $event */
        $event = $this->events[CartMergedEvent::class];

        static::assertEquals(0, $event->getPreviousCart()->getLineItems()->count());
        static::assertEquals($event->getCart()->getName(), $event->getPreviousCart()->getName());
        static::assertEquals($event->getCart()->getToken(), $event->getPreviousCart()->getToken());

        static::assertNotEmpty($p1 = $restoreCart->getLineItems()->get($productId1));
        static::assertEquals(1, $p1->getQuantity());
        static::assertNotEmpty($p2 = $restoreCart->getLineItems()->get($productId2));
        static::assertEquals(5, $p2->getQuantity());
    }

    public function testOrderCriteriaEventIsFired(): void
    {
        $context = Context::createDefaultContext();
        $ids = new TestDataCollection();
        $this->createOrder($ids);

        $this->eventDispatcher->addListener(SalesChannelContextRestorerOrderCriteriaEvent::class, $this->callbackFn);
        $this->contextRestorer->restoreByOrder($ids->create('order'), $context);

        static::assertArrayHasKey(SalesChannelContextRestorerOrderCriteriaEvent::class, $this->events);
        $salesChannelContextRestorerCriteriaEvent = $this->events[SalesChannelContextRestorerOrderCriteriaEvent::class];
        static::assertInstanceOf(SalesChannelContextRestorerOrderCriteriaEvent::class, $salesChannelContextRestorerCriteriaEvent);
    }

    private function createOrder(TestDataCollection $ids): void
    {
        $customer = (new CustomerBuilder($ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'))->build();

        $data = [
            'id' => $ids->create('order'),
            'orderNumber' => Uuid::randomHex(),
            'billingAddressId' => $ids->create('billing-address'),
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'orderDateTime' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'currencyFactor' => 1,
            'stateId' => $this->getStateId('open', 'order.state'),
            'price' => new CartPrice(200, 200, 200, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'ruleIds' => [$ids->get('rule')],
            'orderCustomer' => [
                'id' => $ids->get('customer'),
                'salutationId' => $this->getValidSalutationId(),
                'email' => 'test',
                'firstName' => 'test',
                'lastName' => 'test',
                'customer' => $customer,
            ],
            'addresses' => [
                [
                    'id' => $ids->create('billing-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
                [
                    'id' => $ids->create('shipping-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
            ],
            'lineItems' => [
                [
                    'id' => $ids->create('line-item'),
                    'identifier' => $ids->create('line-item'),
                    'quantity' => 1,
                    'label' => 'label',
                    'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                    'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                ],
            ],
            'deliveries' => [
                [
                    'id' => $ids->create('delivery'),
                    'shippingOrderAddressId' => $ids->create('shipping-address'),
                    'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                    'stateId' => $this->getStateId('open', 'order_delivery.state'),
                    'trackingCodes' => [],
                    'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'positions' => [
                        [
                            'id' => $ids->create('position'),
                            'orderLineItemId' => $ids->create('line-item'),
                            'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        ],
                    ],
                ],
            ],
            'transactions' => [
                [
                    'id' => $ids->create('transaction'),
                    'paymentMethodId' => $this->getPrePaymentMethodId(),
                    'stateId' => $this->getStateId('open', 'order_transaction.state'),
                    'amount' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
        ];

        $this->getContainer()->get('order.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function getStateId(string $state, string $machine)
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchOne('
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ', [
                'state' => $state,
                'machine' => $machine,
            ]);
    }

    private function getPrePaymentMethodId(): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('handlerIdentifier', PrePayment::class));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    private function createProduct(Context $context): string
    {
        $productId = Uuid::randomHex();

        $productNumber = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => $productNumber,
            'stock' => 1,
            'name' => 'Test Product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$data], $context);

        return $productId;
    }

    private function createSalesChannelContext(string $contextToken, array $salesChannelData, ?string $customerId = null): SalesChannelContext
    {
        if ($customerId) {
            $salesChannelData[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL,
            $salesChannelData
        );
    }

    private function cartExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM cart WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchOne();

        return $result > 0;
    }

    private function contextExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM sales_channel_api_context WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchOne();

        return $result > 0;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'foo@bar.de',
                'password' => 'password',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }
}
