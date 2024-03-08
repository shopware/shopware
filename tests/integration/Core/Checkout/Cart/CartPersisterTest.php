<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartPersister::class)]
class CartPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testLoadWithNotExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $eventDispatcher = new EventDispatcher();
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, false);

        $e = null;

        try {
            $persister->load('not_existing_token', Generator::createSalesChannelContext());
        } catch (\Exception $e) {
        }

        static::assertInstanceOf(CartTokenNotFoundException::class, $e);
        static::assertSame('not_existing_token', $e->getParameter('token'));
    }

    public function testLoadWithExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $eventDispatcher = new EventDispatcher();
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->willReturn(
                ['payload' => serialize(new Cart('existing')), 'rule_ids' => json_encode([]), 'compressed' => 0]
            );

        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, false);
        $cart = $persister->load('existing', Generator::createSalesChannelContext());

        static::assertEquals(new Cart('existing'), $cart);
    }

    public function testEmptyCartShouldNotBeSaved(): void
    {
        $connection = $this->createMock(Connection::class);
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);

        $eventDispatcher = new EventDispatcher();

        // Cart should be deleted (in case it exists).
        // Cart should not be inserted or updated.
        $this->expectSqlQuery($connection, 'DELETE FROM `cart`');

        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, false);

        $cart = new Cart('existing');

        $persister->save($cart, Generator::createSalesChannelContext());
    }

    public function testEmptyCartWithManualShippingCostsExtensionIsSaved(): void
    {
        $cart = new Cart('existing');
        $cart->addExtension(
            DeliveryProcessor::MANUAL_SHIPPING_COSTS,
            new CalculatedPrice(
                20.0,
                20.0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            )
        );

        $this->getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotEmpty($token);
    }

    public function testEmptyCartWithCustomerCommentIsSaved(): void
    {
        $cart = new Cart('existing');
        $cart->setCustomerComment('Foo');

        $this->getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotEmpty($token);
    }

    public function testSaveWithItems(): void
    {
        $cart = new Cart('existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $this->getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotEmpty($token);
    }

    public function testRecalculationCartShouldNotBeSaved(): void
    {
        $cartBehavior = new CartBehavior([], true, true);

        $cart = new Cart('existing');
        $cart->setBehavior($cartBehavior);
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $this->getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertFalse($token);
    }

    public function testCartSavedEventIsFired(): void
    {
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $caughtEvent = null;
        $this->addEventListener($eventDispatcher, CartSavedEvent::class, static function (CartSavedEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $cart = new Cart('existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $this->getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotEmpty($token);

        static::assertInstanceOf(CartSavedEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getCart()->getLineItems());
        $firstLineItem = $caughtEvent->getCart()->getLineItems()->first();
        static::assertInstanceOf(LineItem::class, $firstLineItem);
        static::assertSame('test', $firstLineItem->getLabel());
    }

    public function testCartCanBeUnserialized(): void
    {
        $cart = unserialize((string) file_get_contents(__DIR__ . '/fixtures/cart.blob'));

        static::assertInstanceOf(Cart::class, $cart);
    }

    public function testCartVerifyPersistEventIsFiredAndNotPersisted(): void
    {
        $connection = $this->createMock(Connection::class);
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $eventDispatcher = new EventDispatcher();

        $this->expectSqlQuery($connection, 'DELETE FROM `cart`');

        $caughtEvent = null;
        $this->addEventListener($eventDispatcher, CartVerifyPersistEvent::class, function (CartVerifyPersistEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, false);

        $cart = new Cart('existing');

        $persister->save(
            $cart,
            $this->getSalesChannelContext($cart->getToken())
        );
        static::assertInstanceOf(CartVerifyPersistEvent::class, $caughtEvent, CartVerifyPersistEvent::class . ' did not run');
        static::assertFalse($caughtEvent->shouldBePersisted());
        static::assertCount(0, $caughtEvent->getCart()->getLineItems());
    }

    public function testCartVerifyPersistEventIsFiredAndPersisted(): void
    {
        $caughtEvent = null;
        $this->addEventListener($this->getContainer()->get('event_dispatcher'), CartVerifyPersistEvent::class, static function (CartVerifyPersistEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $cart = new Cart('existing');
        $cart->addLineItems(new LineItemCollection([
            new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex(), 1),
        ]));

        $this->getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotEmpty($token);

        static::assertInstanceOf(CartVerifyPersistEvent::class, $caughtEvent);
        static::assertTrue($caughtEvent->shouldBePersisted());
        static::assertCount(1, $caughtEvent->getCart()->getLineItems());
    }

    public function testCartVerifyPersistEventIsFiredAndModified(): void
    {
        $caughtEvent = null;
        $this->addEventListener($this->getContainer()->get('event_dispatcher'), CartVerifyPersistEvent::class, static function (CartVerifyPersistEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
            $event->setShouldPersist(false);
        });

        $cart = new Cart('existing');
        $cart->addLineItems(new LineItemCollection([
            new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex(), 1),
        ]));

        $this->getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertEmpty($token);

        static::assertInstanceOf(CartVerifyPersistEvent::class, $caughtEvent);
        static::assertFalse($caughtEvent->shouldBePersisted());
        static::assertCount(1, $caughtEvent->getCart()->getLineItems());
    }

    public function testPrune(): void
    {
        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM cart');

        $ids = new IdsCollection();

        $now = new \DateTimeImmutable();

        $this->createCart($ids->create('cart-1'), $now);

        $expiredDate1 = $now->modify(sprintf('-%d day', 121));
        $this->createCart($ids->create('cart-2'), $expiredDate1);

        $this->createCart($ids->create('cart-3'), $expiredDate1, $now);

        $expiredDate2 = $now->modify(sprintf('-%d day', 122));
        $this->createCart($ids->create('cart-4'), $expiredDate2, $expiredDate1);

        $this->getContainer()->get(CartPersister::class)->prune(30);

        $carts = $this->getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT token FROM cart');

        static::assertCount(2, $carts);
        static::assertContains($ids->get('cart-1'), $carts);
        static::assertContains($ids->get('cart-3'), $carts);
    }

    private function getSalesChannelContext(string $token): SalesChannelContext
    {
        return $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create($token, TestDefaults::SALES_CHANNEL);
    }

    private function expectSqlQuery(MockObject $connection, string $beginOfSql): void
    {
        $connection->expects(static::once())
            ->method('prepare')
            ->with(
                static::callback(fn (string $sql): bool => \str_starts_with(\trim($sql), $beginOfSql))
            )
            ->willReturnCallback(fn (string $sql): Statement => $this->getContainer()->get(Connection::class)->prepare($sql));
    }

    private function createCart(string $token, \DateTimeImmutable $date, ?\DateTimeImmutable $updatedAt = null): void
    {
        $cart = [
            'token' => $token,
            'payload' => '',
            'rule_ids' => json_encode([]),
            'created_at' => $updatedAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT) ?? $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->getContainer()->get(Connection::class)
            ->insert('cart', $cart);
    }
}
