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
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
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

    /**
     * @var Connection
     */
    private $connection;

    private CartRestorer $cartRestorer;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var array<string, Event>
     */
    private array $events;

    /**
     * @var \Closure
     */
    private $callbackFn;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    private string $customerId;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->cartService = $this->getContainer()->get(CartService::class);

        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $this->events = [];

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        $this->contextPersister = $this->getContainer()->get(SalesChannelContextPersister::class);
        /** @var AbstractSalesChannelContextFactory $contextFactory */
        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $cartRuleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $requestStack = $this->getContainer()->get(RequestStack::class);

        $this->customerId = $this->createCustomer()->getId();

        $this->cartRestorer = new CartRestorer(
            $contextFactory,
            $this->contextPersister,
            $this->cartService,
            $cartRuleLoader,
            $this->eventDispatcher,
            $requestStack
        );
    }

    public function testRestoreByToken(): void
    {
        $currentContext = $this->createSalesChannelContext('currentToken', $this->customerId);

        $this->contextPersister->save($currentContext->getToken(), [], $currentContext->getSalesChannel()->getId());

        $this->eventDispatcher->addListener(SalesChannelContextRestoredEvent::class, $this->callbackFn);

        $restoredContext = $this->cartRestorer->restoreByToken($currentContext->getToken(), $this->customerId, $currentContext);

        static::assertSame($currentContext->getToken(), $restoredContext->getToken());

        static::assertArrayHasKey(SalesChannelContextRestoredEvent::class, $this->events);
        $salesChannelRestoredEvent = $this->events[SalesChannelContextRestoredEvent::class];
        static::assertInstanceOf(SalesChannelContextRestoredEvent::class, $salesChannelRestoredEvent);
    }

    public function testRestoreByTokenWithNotExistingToken(): void
    {
        $currentContext = $this->createSalesChannelContext('currentToken', $this->customerId);

        $this->eventDispatcher->addListener(SalesChannelContextRestoredEvent::class, $this->callbackFn);

        $restoredContext = $this->cartRestorer->restoreByToken($currentContext->getToken(), $this->customerId, $currentContext);

        static::assertSame($currentContext->getToken(), $restoredContext->getToken());

        static::assertArrayNotHasKey(SalesChannelContextRestoredEvent::class, $this->events);
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
        $currentContextToken = Random::getAlphanumericString(32);

        $currentContext = $this->createSalesChannelContext($currentContextToken);

        $this->contextPersister->save($currentContextToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $cart = new Cart($currentContextToken);

        $cart->add(new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE));
        $this->getContainer()->get(CartPersister::class)->save($cart, $currentContext);

        static::assertTrue($this->cartExists($currentContextToken));
        static::assertTrue($this->contextExists($currentContextToken));

        $newContext = $this->cartRestorer->restore($this->customerId, $currentContext);

        static::assertTrue($this->cartExists($newContext->getToken()));
        static::assertTrue($this->contextExists($newContext->getToken()));

        static::assertFalse($this->cartExists($currentContextToken));
        static::assertFalse($this->contextExists($currentContextToken));
    }

    public function testCartIsRecalculated(): void
    {
        $customerContextToken = Random::getAlphanumericString(32);

        $customerContext = $this->createSalesChannelContext($customerContextToken);

        $this->contextPersister->save($customerContextToken, [], $customerContext->getSalesChannel()->getId(), $this->customerId);

        $cart = new Cart($customerContextToken);

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

        $guestContext = $this->createSalesChannelContext('123123');

        $restoreContext = $this->cartRestorer->restore($this->customerId, $guestContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        // Delete product will removed from cart as result from recalculation
        static::assertCount(0, $restoreCart->getLineItems());
    }

    public function testCartIsMergedAndRecalculatedWithTheSavedOne(): void
    {
        $currentContextToken = Random::getAlphanumericString(32);

        $currentContext = $this->createSalesChannelContext($currentContextToken);

        // Create Guest cart
        $cart = new Cart($currentContextToken);

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
        $customerContext = $this->createSalesChannelContext($customerToken);

        $this->contextPersister->save($customerToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $cart = new Cart($customerToken);

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

        $restoreContext = $this->cartRestorer->restore($this->customerId, $currentContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        // Delete product will removed from cart as result from recalculation
        static::assertEmpty($restoreCart->getLineItems()->get($productId3));

        static::assertArrayHasKey(CartMergedEvent::class, $this->events);
        $cartMergedEvent = $this->events[CartMergedEvent::class];
        static::assertInstanceOf(CartMergedEvent::class, $cartMergedEvent);

        static::assertEquals(1, $cartMergedEvent->getPreviousCart()->getLineItems()->count());
        static::assertEquals($cartMergedEvent->getCart()->getToken(), $cartMergedEvent->getPreviousCart()->getToken());

        static::assertNotNull($p1 = $restoreCart->getLineItems()->get($productId1));
        static::assertEquals(1, $p1->getQuantity());
        static::assertNotNull($savedItem = $restoreCart->getLineItems()->get($savedLineItem->getId()));
        static::assertEquals($savedLineItemQuantity + $guestProductQuantity, $savedItem->getQuantity());
    }

    public function testCartMergedEventIsFiredWithCustomerCart(): void
    {
        $currentContextToken = Random::getAlphanumericString(32);

        $currentContext = $this->createSalesChannelContext($currentContextToken);

        // Create Guest cart
        $cart = new Cart($currentContextToken);

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
        $customerContext = $this->createSalesChannelContext($customerToken);

        $this->contextPersister->save($customerToken, [], $currentContext->getSalesChannel()->getId(), $this->customerId);

        $cart = new Cart($customerToken);

        $this->getContainer()->get(CartPersister::class)->save($cart, $customerContext);

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

        static::assertNotNull($p1 = $restoreCart->getLineItems()->get($productId1));
        static::assertEquals(1, $p1->getQuantity());
        static::assertNotNull($p2 = $restoreCart->getLineItems()->get($productId2));
        static::assertEquals(5, $p2->getQuantity());
    }

    public function testPermissionsAreIgnoredOnRestore(): void
    {
        $currentContextToken = Random::getAlphanumericString(32);

        $currentContext = $this->createSalesChannelContext($currentContextToken);

        $con = $this->getContainer()->get(Connection::class);

        $con->insert('sales_channel_api_context', [
            'token' => Random::getAlphanumericString(32),
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
        $this->getContainer()->get('product.repository')->create([$data], $context);

        return $productId;
    }

    private function createSalesChannelContext(string $contextToken, ?string $customerId = null): SalesChannelContext
    {
        $salesChannelData = [];
        if ($customerId) {
            $salesChannelData[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
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

        $entity = $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(CustomerEntity::class, $entity);

        return $entity;
    }
}
