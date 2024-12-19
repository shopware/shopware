<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Event\BeforeCartMergeEvent;
use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class CartRestorerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private CartRestorer $cartRestorer;

    private CartService $cartService;

    private CartPersister $cartPersister;

    /**
     * @var array<string, Event>
     */
    private array $events;

    private \Closure $callbackFn;

    private EventDispatcherInterface $eventDispatcher;

    private SalesChannelContextPersister $contextPersister;

    private string $customerId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->cartPersister = static::getContainer()->get(CartPersister::class);

        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->events = [];

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        $this->contextPersister = static::getContainer()->get(SalesChannelContextPersister::class);
        /** @var AbstractSalesChannelContextFactory $contextFactory */
        $contextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);
        $requestStack = static::getContainer()->get(RequestStack::class);

        $this->customerId = $this->createCustomer()->getId();

        $this->cartRestorer = new CartRestorer(
            $contextFactory,
            $this->contextPersister,
            $this->cartService,
            $cartRuleLoader,
            $this->cartPersister,
            $this->eventDispatcher,
            $requestStack
        );
    }

    /**
     * Check if a cart is restored correctly by its token. The cart should be restored with the same line items and
     * keep its token.
     *
     * @throws \JsonException
     */
    public function testRestoreByToken(): void
    {
        $currentContextToken = Uuid::randomHex();
        $currentContext = $this->createSalesChannelContext($currentContextToken, $this->customerId);

        $guestToken = Uuid::randomHex();
        $guestContext = $this->createSalesChannelContext($guestToken, $this->customerId);

        $this->contextPersister->save($currentContextToken, [], $currentContext->getSalesChannelId());
        $this->contextPersister->save($guestToken, [], $guestContext->getSalesChannelId());

        $this->eventDispatcher->addListener(SalesChannelContextRestoredEvent::class, $this->callbackFn);

        $productLineItem1 = $this->createLineItem($currentContext, 2);
        $productLineItem2 = $this->createLineItem($currentContext, 3);

        $cart = $this->createAndSaveUnmodifiedCart($currentContext, $productLineItem1, $productLineItem2);

        $restoredContext = $this->cartRestorer->restoreByToken($currentContextToken, $this->customerId, $guestContext);

        static::assertSame($currentContext->getToken(), $restoredContext->getToken());

        static::assertArrayHasKey(SalesChannelContextRestoredEvent::class, $this->events);
        $salesChannelRestoredEvent = $this->events[SalesChannelContextRestoredEvent::class];
        static::assertInstanceOf(SalesChannelContextRestoredEvent::class, $salesChannelRestoredEvent);

        $restoredCart = $this->cartService->getCart($restoredContext->getToken(), $restoredContext);
        $restoredLineItem1 = $restoredCart->getLineItems()->get($productLineItem1->getId());
        $restoredLineItem2 = $restoredCart->getLineItems()->get($productLineItem2->getId());

        static::assertInstanceOf(LineItem::class, $restoredLineItem1);
        static::assertInstanceOf(LineItem::class, $restoredLineItem2);
        static::assertFalse($restoredCart->isModified());

        static::assertSame($productLineItem1->getQuantity(), $restoredLineItem1->getQuantity());
        static::assertSame(2, $restoredLineItem1->getQuantity());
        static::assertSame($productLineItem2->getQuantity(), $restoredLineItem2->getQuantity());
        static::assertSame(3, $restoredLineItem2->getQuantity());
    }

    public function testRestoreByTokenIsMergedWithGuestCart(): void
    {
        // Create Guest cart
        $guestProductQuantity = 3;
        ['context' => $guestContext, 'cart' => $guestCart] = $this->createContextAndFilledCart(1, $guestProductQuantity);
        $guestProductLineItem1 = $guestCart->getLineItems()->getAt(0);
        $guestProductLineItem2 = $guestCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $guestProductLineItem1);
        static::assertInstanceOf(LineItem::class, $guestProductLineItem2);

        // Create Saved Customer cart
        $customerLineItemQuantity = 4;
        ['context' => $customerContext, 'cart' => $customerCart] = $this->createContextAndFilledCart($customerLineItemQuantity, 3, true);
        $customerToken = $customerContext->getToken();
        $customerLineItem1 = $customerCart->getLineItems()->getAt(0);
        $customerLineItem2 = $customerCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $customerLineItem1);
        static::assertInstanceOf(LineItem::class, $customerLineItem2);

        $combinedLineItemsCount = $guestCart->getLineItems()->count() + $customerCart->getLineItems()->count();

        $restoredContext = $this->cartRestorer->restoreByToken(
            $customerToken,
            $this->customerId,
            $guestContext
        );

        $restoredCart = $this->cartService->getCart($restoredContext->getToken(), $restoredContext);
        $restoredLineItems = $restoredCart->getLineItems();

        static::assertFalse($restoredCart->isModified());
        static::assertCount($combinedLineItemsCount, $restoredLineItems);

        static::assertIsString($guestProductLineItem1->getReferencedId());
        static::assertIsString($guestProductLineItem2->getReferencedId());
        static::assertIsString($customerLineItem1->getReferencedId());
        static::assertIsString($customerLineItem2->getReferencedId());

        $restoredGuestProductLineItem1 = $restoredLineItems->get($guestProductLineItem1->getReferencedId());
        static::assertNotNull($restoredGuestProductLineItem1);
        static::assertSame(1, $restoredGuestProductLineItem1->getQuantity());

        $restoredGuestProductLineItem2 = $restoredLineItems->get($guestProductLineItem2->getReferencedId());
        static::assertNotNull($restoredGuestProductLineItem2);
        static::assertSame($guestProductQuantity, $restoredGuestProductLineItem2->getQuantity());

        $restoredCustomerLineItem1 = $restoredLineItems->get($customerLineItem1->getReferencedId());
        static::assertNotNull($restoredCustomerLineItem1);
        static::assertSame($customerLineItemQuantity, $restoredCustomerLineItem1->getQuantity());

        $restoredCustomerLineItem2 = $restoredLineItems->get($customerLineItem2->getReferencedId());
        static::assertNotNull($restoredCustomerLineItem2);
        static::assertSame(3, $restoredCustomerLineItem2->getQuantity());
    }

    public function testRestoreByTokenWithNotExistingToken(): void
    {
        $formerContext = $this->createSalesChannelContext('formerToken', $this->customerId);
        $currentContext = $this->createSalesChannelContext('currentToken', $this->customerId);

        $this->eventDispatcher->addListener(SalesChannelContextRestoredEvent::class, $this->callbackFn);

        $restoredContext = $this->cartRestorer->restoreByToken($formerContext->getToken(), $this->customerId, $currentContext);

        static::assertSame($formerContext->getToken(), $restoredContext->getToken());

        static::assertArrayNotHasKey(SalesChannelContextRestoredEvent::class, $this->events);
    }

    public function testRestoreByTokenIsReplacedWithNoExistingToken(): void
    {
        // Create Guest cart
        ['context' => $guestContext, 'cart' => $guestCart] = $this->createContextAndFilledCart(3, 4);
        $guestProductLineItem1 = $guestCart->getLineItems()->getAt(0);
        $guestProductLineItem2 = $guestCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $guestProductLineItem1);
        static::assertInstanceOf(LineItem::class, $guestProductLineItem2);

        $currentContext = $this->createSalesChannelContext('currentToken', $this->customerId);

        $restoredContext = $this->cartRestorer->restoreByToken(
            $currentContext->getToken(),
            $this->customerId,
            $guestContext
        );

        static::assertSame($currentContext->getToken(), $restoredContext->getToken());

        $restoredCart = $this->cartService->getCart($restoredContext->getToken(), $restoredContext);
        $restoredLineItems = $restoredCart->getLineItems();

        static::assertFalse($restoredCart->isModified());
        static::assertSame(7, $restoredLineItems->getTotalQuantity());

        static::assertIsString($guestProductLineItem1->getReferencedId());
        static::assertIsString($guestProductLineItem2->getReferencedId());

        $restoredGuestProductLineItem1 = $restoredLineItems->get($guestProductLineItem1->getReferencedId());
        static::assertNotNull($restoredGuestProductLineItem1);
        static::assertSame(3, $restoredGuestProductLineItem1->getQuantity());

        $restoredGuestProductLineItem2 = $restoredLineItems->get($guestProductLineItem2->getReferencedId());
        static::assertNotNull($restoredGuestProductLineItem2);
        static::assertSame(4, $restoredGuestProductLineItem2->getQuantity());
    }

    public function testRestoreByTokenIsReplacedWhenExpired(): void
    {
        // Create Guest cart
        $guestProductQuantity = 5;
        ['context' => $guestContext, 'cart' => $guestCart] = $this->createContextAndFilledCart(3, $guestProductQuantity);
        $guestProductLineItem1 = $guestCart->getLineItems()->getAt(0);
        $guestProductLineItem2 = $guestCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $guestProductLineItem1);
        static::assertInstanceOf(LineItem::class, $guestProductLineItem2);

        // Create Saved Customer cart
        $customerLineItemQuantity = 2;
        ['context' => $customerContext, 'cart' => $customerCart] = $this->createContextAndFilledCart($customerLineItemQuantity, 3, true);
        $customerToken = $customerContext->getToken();

        $customerLineItem1 = $customerCart->getLineItems()->getAt(0);
        $customerLineItem2 = $customerCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $customerLineItem1);
        static::assertInstanceOf(LineItem::class, $customerLineItem2);

        $this->connection->executeStatement(<<<SQL
            UPDATE sales_channel_api_context
            SET updated_at = DATE_SUB(updated_at, INTERVAL 7 DAY)
            WHERE token = :token
            SQL
            , ['token' => $customerToken]);

        $restoredContext = $this->cartRestorer->restoreByToken(
            $customerToken,
            $this->customerId,
            $guestContext
        );

        static::assertArrayNotHasKey(SalesChannelContextRestoredEvent::class, $this->events);

        $restoredCart = $this->cartService->getCart($restoredContext->getToken(), $restoredContext);
        $restoredLineItems = $restoredCart->getLineItems();

        static::assertFalse($restoredCart->isModified());
        static::assertCount(2, $restoredLineItems);
        static::assertSame(3 + $guestProductQuantity, $restoredLineItems->getTotalQuantity());

        static::assertIsString($guestProductLineItem1->getReferencedId());
        static::assertIsString($guestProductLineItem2->getReferencedId());
        static::assertIsString($customerLineItem1->getReferencedId());
        static::assertIsString($customerLineItem2->getReferencedId());

        $restoredGuestProductLineItem1 = $restoredLineItems->get($guestProductLineItem1->getReferencedId());
        static::assertNotNull($restoredGuestProductLineItem1);
        static::assertSame(3, $restoredGuestProductLineItem1->getQuantity());

        $restoredGuestProductLineItem2 = $restoredLineItems->get($guestProductLineItem2->getReferencedId());
        static::assertNotNull($restoredGuestProductLineItem2);
        static::assertSame($guestProductQuantity, $restoredGuestProductLineItem2->getQuantity());

        $restoredCustomerLineItem1 = $restoredLineItems->get($customerLineItem1->getReferencedId());
        static::assertNull($restoredCustomerLineItem1);

        $restoredCustomerLineItem2 = $restoredLineItems->get($customerLineItem2->getReferencedId());
        static::assertNull($restoredCustomerLineItem2);
    }

    public function testRestore(): void
    {
        $expectedToken = Uuid::randomHex();
        $expectedContext = $this->createSalesChannelContext($expectedToken);

        $currentContext = $this->createSalesChannelContext('currentToken', $this->customerId);

        $this->contextPersister->save($expectedContext->getToken(), [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $this->eventDispatcher->addListener(SalesChannelContextRestoredEvent::class, $this->callbackFn);

        $restoredContext = $this->cartRestorer->restore($this->customerId, $currentContext);

        static::assertSame($expectedContext->getToken(), $restoredContext->getToken());

        static::assertArrayHasKey(SalesChannelContextRestoredEvent::class, $this->events);
        $salesChannelRestoredEvent = $this->events[SalesChannelContextRestoredEvent::class];
        static::assertInstanceOf(SalesChannelContextRestoredEvent::class, $salesChannelRestoredEvent);
    }

    public function testGuestContextAndCartAreDeleted(): void
    {
        $currentContextToken = Uuid::randomHex();

        $currentContext = $this->createSalesChannelContext($currentContextToken);

        $this->contextPersister->save($currentContextToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $cart = $this->createAndSaveUnmodifiedCart(
            $currentContext,
            $this->createLineItem($currentContext)->setType(LineItem::CUSTOM_LINE_ITEM_TYPE)
        );

        $newContext = $this->cartRestorer->restore($this->customerId, $currentContext);

        static::assertTrue($this->cartExists($newContext->getToken()));
        static::assertTrue($this->contextExists($newContext->getToken()));

        static::assertFalse($this->cartExists($currentContextToken));
        static::assertFalse($this->contextExists($currentContextToken));
    }

    public function testCartIsRecalculated(): void
    {
        $customerContextToken = Uuid::randomHex();

        $customerContext = $this->createSalesChannelContext($customerContextToken);

        $this->contextPersister->save($customerContextToken, [], $customerContext->getSalesChannel()->getId(), $this->customerId);

        $productLineItem = $this->createLineItem($customerContext, 1);
        $productLineItem->setId(Uuid::randomHex());
        $cart = $this->createAndSaveUnmodifiedCart($customerContext, $productLineItem);

        static::assertCount(1, $cart->getLineItems());

        static::getContainer()->get('product.repository')->delete([[
            'id' => $productLineItem->getReferencedId(),
        ]], $customerContext->getContext());

        $guestContext = $this->createSalesChannelContext('123123');

        $restoreContext = $this->cartRestorer->restore($this->customerId, $guestContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        // The deleted product will be removed from the cart as a result of recalculation
        static::assertCount(0, $restoreCart->getLineItems());
    }

    public function testCartIsMergedAndRecalculatedWithTheSavedOne(): void
    {
        $currentContextToken = Uuid::randomHex();
        $currentContext = $this->createSalesChannelContext($currentContextToken);
        $this->contextPersister->save($currentContextToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $guestProductQuantity = 5;
        $productLineItem1 = $this->createLineItem($currentContext, 1);
        $productLineItem2 = $this->createLineItem($currentContext, $guestProductQuantity);

        // Create Guest cart
        $guestCart = $this->createAndSaveUnmodifiedCart(
            $currentContext,
            $productLineItem1,
            $productLineItem2
        );

        // Create Saved Customer cart
        $customerToken = Uuid::randomHex();
        $customerContext = $this->createSalesChannelContext($customerToken);
        $this->contextPersister->save($customerToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $savedLineItemQuantity = 4;
        $savedLineItem = $this->createLineItem($customerContext, $savedLineItemQuantity);
        static::assertIsString($productLineItem2->getReferencedId());
        $savedLineItem->setId($productLineItem2->getReferencedId());
        $savedLineItem->setReferencedId($productLineItem2->getReferencedId());

        $productLineItem3 = $this->createLineItem($customerContext, 3);

        $customerCart = $this->createAndSaveUnmodifiedCart(
            $customerContext,
            $savedLineItem,
            $productLineItem3
        );

        // Delete 1 saved item
        static::getContainer()->get('product.repository')->delete([[
            'id' => $productLineItem3->getReferencedId(),
        ]], $customerContext->getContext());

        $this->eventDispatcher->addListener(CartMergedEvent::class, $this->callbackFn);

        $restoreContext = $this->cartRestorer->restore($this->customerId, $currentContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        static::assertIsString($productLineItem3->getReferencedId());
        // The deleted product will be removed from the cart as a result of recalculation
        static::assertEmpty($restoreCart->getLineItems()->get($productLineItem3->getReferencedId()));

        static::assertArrayHasKey(CartMergedEvent::class, $this->events);
        $cartMergedEvent = $this->events[CartMergedEvent::class];
        static::assertInstanceOf(CartMergedEvent::class, $cartMergedEvent);

        static::assertEquals(1, $cartMergedEvent->getPreviousCart()->getLineItems()->count());
        static::assertEquals($cartMergedEvent->getCart()->getToken(), $cartMergedEvent->getPreviousCart()->getToken());

        static::assertIsString($productLineItem1->getReferencedId());
        static::assertNotNull($p1 = $restoreCart->getLineItems()->get($productLineItem1->getReferencedId()));
        static::assertEquals(1, $p1->getQuantity());
        static::assertNotNull($savedItem = $restoreCart->getLineItems()->get($savedLineItem->getId()));
        static::assertEquals($savedLineItemQuantity + $guestProductQuantity, $savedItem->getQuantity());
    }

    public function testCartMergedEventIsFiredWithCustomerCart(): void
    {
        $currentContextToken = Uuid::randomHex();
        $currentContext = $this->createSalesChannelContext($currentContextToken);
        $this->contextPersister->save($currentContextToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        // Create Guest cart
        $guestProductQuantity = 5;
        $productLineItem1 = $this->createLineItem($currentContext, 1);
        $productLineItem2 = $this->createLineItem($currentContext, $guestProductQuantity);
        $guestCart = $this->createAndSaveUnmodifiedCart(
            $currentContext,
            $productLineItem1,
            $productLineItem2
        );

        // Create Saved Customer cart
        $customerToken = Uuid::randomHex();
        $customerContext = $this->createSalesChannelContext($customerToken);

        $this->contextPersister->save($customerToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $customerCart = new Cart($customerToken);
        $this->cartPersister->save($customerCart, $customerContext);

        $this->eventDispatcher->addListener(BeforeCartMergeEvent::class, $this->callbackFn);
        $this->eventDispatcher->addListener(CartMergedEvent::class, $this->callbackFn);

        $restoreContext = $this->cartRestorer->restore($this->customerId, $currentContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        static::assertArrayHasKey(BeforeCartMergeEvent::class, $this->events);
        static::assertArrayHasKey(CartMergedEvent::class, $this->events);

        /** @var CartMergedEvent $event */
        $event = $this->events[CartMergedEvent::class];

        static::assertNotNull($event->getPreviousCart());
        static::assertEquals(0, $event->getPreviousCart()->getLineItems()->count());
        static::assertEquals($event->getCart()->getToken(), $event->getPreviousCart()->getToken());

        static::assertIsString($productLineItem1->getReferencedId());
        static::assertNotNull($p1 = $restoreCart->getLineItems()->get($productLineItem1->getReferencedId()));
        static::assertSame(1, $p1->getQuantity());

        static::assertIsString($productLineItem2->getReferencedId());
        static::assertNotNull($p2 = $restoreCart->getLineItems()->get($productLineItem2->getReferencedId()));
        static::assertSame($guestProductQuantity, $p2->getQuantity());
    }

    public function testPermissionsAreIgnoredOnRestore(): void
    {
        $currentContextToken = Uuid::randomHex();

        $currentContext = $this->createSalesChannelContext($currentContextToken);

        $con = static::getContainer()->get(Connection::class);

        $con->insert('sales_channel_api_context', [
            'token' => Uuid::randomHex(),
            'payload' => \json_encode(['expired' => false, 'customerId' => $this->customerId, 'permissions' => ['foo']], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => Uuid::fromHexToBytes($currentContext->getSalesChannelId()),
            'customer_id' => Uuid::fromHexToBytes($this->customerId),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $restoreContext = $this->cartRestorer->restore($this->customerId, $currentContext);

        static::assertSame([], $restoreContext->getPermissions());
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
        static::getContainer()->get('product.repository')->create([$data], $context);

        return $productId;
    }

    private function createSalesChannelContext(string $contextToken, ?string $customerId = null): SalesChannelContext
    {
        $salesChannelData = [];
        if ($customerId) {
            $salesChannelData[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        return static::getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL
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

        $customer = [
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'foo@bar.de',
            'password' => 'password',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $repo = static::getContainer()->get('customer.repository');

        $repo->create([$customer], Context::createDefaultContext());

        $entity = $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(CustomerEntity::class, $entity);

        return $entity;
    }

    private function createLineItem(
        SalesChannelContext $context,
        ?int $quantity = null
    ): LineItem {
        $productId = $this->createProduct($context->getContext());

        $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);
        $productLineItem->setStackable(true);

        if ($quantity !== null) {
            $productLineItem->setQuantity($quantity);
        }

        return $productLineItem;
    }

    /**
     * Create a new cart with the provided line items and save it to the database. The cart must contain at least one
     * $lineItems, else it won't be saved and the included assertion will fail.
     */
    private function createAndSaveUnmodifiedCart(SalesChannelContext $context, LineItem ...$lineItems): Cart
    {
        $cart = new Cart($context->getToken());

        $cart->addLineItems(new LineItemCollection($lineItems));
        $cart->markUnmodified();

        $this->cartPersister->save($cart, $context);

        static::assertTrue($this->cartExists($context->getToken()));
        static::assertTrue($this->contextExists($context->getToken()));

        return $cart;
    }

    /**
     * @return array{context:SalesChannelContext, cart:Cart}
     */
    private function createContextAndFilledCart(
        ?int $firstLineItemCount = 1,
        ?int $secondLIneItemCount = 3,
        ?bool $withCustomerId = false,
    ): array {
        $contextToken = Uuid::randomHex();
        $contextCustomerId = $withCustomerId ? $this->customerId : null;
        $context = $this->createSalesChannelContext($contextToken, $contextCustomerId);
        $this->contextPersister->save(
            $contextToken,
            [],
            $context->getSalesChannelId(),
            $contextCustomerId
        );

        $productLineItem1 = $this->createLineItem($context, $firstLineItemCount);
        $productLineItem2 = $this->createLineItem($context, $secondLIneItemCount);

        $cart = $this->createAndSaveUnmodifiedCart($context, $productLineItem1, $productLineItem2);

        return ['context' => $context, 'cart' => $cart];
    }
}
