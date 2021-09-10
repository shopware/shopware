<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CartPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testLoadWithNotExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $persister = new CartPersister($connection, $eventDispatcher);

        $e = null;

        try {
            $persister->load('not_existing_token', Generator::createSalesChannelContext());
        } catch (\Exception $e) {
        }

        static::assertInstanceOf(CartTokenNotFoundException::class, $e);
        static::assertSame('not_existing_token', $e->getToken());
    }

    public function testLoadWithExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->willReturn(
                ['cart' => serialize(new Cart('shopware', 'existing')), 'rule_ids' => json_encode([])]
            );

        $persister = new CartPersister($connection, $eventDispatcher);
        $cart = $persister->load('existing', Generator::createSalesChannelContext());

        static::assertEquals(new Cart('shopware', 'existing'), $cart);
    }

    public function testEmptyCartShouldNotBeSaved(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();

        // Cart should be deleted (in case it exists).
        $connection->expects(static::once())->method('delete');

        // Cart should not be inserted or updated.
        $connection->expects(static::never())->method('executeUpdate');

        $persister = new CartPersister($connection, $eventDispatcher);

        $cart = new Cart('shopware', 'existing');

        $persister->save($cart, Generator::createSalesChannelContext());
    }

    public function testEmptyCartWithManualShippingCostsExtensionIsSaved(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();

        // Cart should be not be deleted.
        $connection->expects(static::never())->method('delete');

        // Cart should be inserted or updated.
        $connection->expects(static::once())->method('executeUpdate');

        $persister = new CartPersister($connection, $eventDispatcher);

        $cart = new Cart('shopware', 'existing');
        $cart->addExtension(
            DeliveryProcessor::MANUAL_SHIPPING_COSTS,
            new CalculatedPrice(
                20.0,
                20.0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            )
        );

        $persister->save($cart, Generator::createSalesChannelContext());
    }

    public function testEmptyCartWithCustomerCommentIsSaved(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();

        // Cart should be not be deleted.
        $connection->expects(static::never())->method('delete');

        // Cart should be inserted or updated.
        $connection->expects(static::once())->method('executeUpdate');

        $persister = new CartPersister($connection, $eventDispatcher);

        $cart = new Cart('shopware', 'existing');
        $cart->setCustomerComment('Foo');

        $persister->save($cart, Generator::createSalesChannelContext());
    }

    public function testSaveWithItems(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();

        // Verify that cart deletion is never called.
        $connection->expects(static::never())->method('delete');

        // Check that cart insert or update is called.
        $connection->expects(static::once())->method('executeUpdate');

        $persister = new CartPersister($connection, $eventDispatcher);

        $cart = new Cart('shopware', 'existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister->save($cart, Generator::createSalesChannelContext());
    }

    public function testCartSavedEventIsFired(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $connection->expects(static::once())->method('executeUpdate');

        $caughtEvent = null;
        $eventDispatcher->addListener(CartSavedEvent::class, static function (CartSavedEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $persister = new CartPersister($connection, $eventDispatcher);

        $cart = new Cart('shopware', 'existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister->save($cart, Generator::createSalesChannelContext());

        static::assertInstanceOf(CartSavedEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getCart()->getLineItems());
        $firstLineItem = $caughtEvent->getCart()->getLineItems()->first();
        static::assertNotNull($firstLineItem);
        static::assertSame('test', $firstLineItem->getLabel());
    }

    public function testCartCanBeUnserialized(): void
    {
        $cart = unserialize(file_get_contents(__DIR__ . '/fixtures/cart.blob'));
        static::assertInstanceOf(Cart::class, $cart);
    }

    public function testCartVerifyPersistEventIsFiredAndNotPersisted(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();

        $connection->expects(static::never())->method('executeUpdate');
        $connection->expects(static::once())->method('delete');

        $caughtEvent = null;
        $eventDispatcher->addListener(CartVerifyPersistEvent::class, function (CartVerifyPersistEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $persister = new CartPersister($connection, $eventDispatcher);

        $cart = new Cart('shopware', 'existing');

        $persister->save($cart, Generator::createSalesChannelContext());
        static::assertInstanceOf(CartVerifyPersistEvent::class, $caughtEvent, CartVerifyPersistEvent::class . ' did not run');
        static::assertFalse($caughtEvent->shouldBePersisted());
        static::assertCount(0, $caughtEvent->getCart()->getLineItems());
    }

    public function testCartVerifyPersistEventIsFiredAndPersisted(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();

        $connection->expects(static::once())->method('executeUpdate');
        $connection->expects(static::never())->method('delete');

        $caughtEvent = null;
        $eventDispatcher->addListener(CartVerifyPersistEvent::class, static function (CartVerifyPersistEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $persister = new CartPersister($connection, $eventDispatcher);

        $cart = new Cart('shopware', 'existing');
        $cart->addLineItems(new LineItemCollection([
            new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex(), 1),
        ]));

        $persister->save($cart, Generator::createSalesChannelContext());

        static::assertInstanceOf(CartVerifyPersistEvent::class, $caughtEvent);
        static::assertTrue($caughtEvent->shouldBePersisted());
        static::assertCount(1, $caughtEvent->getCart()->getLineItems());
    }

    public function testCartVerifyPersistEventIsFiredAndModified(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();

        $caughtEvent = null;
        $handler = static function (CartVerifyPersistEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
            $event->setShouldPersist(false);
        };
        $eventDispatcher->addListener(CartVerifyPersistEvent::class, $handler);

        $persister = new CartPersister($connection, $eventDispatcher);

        $cart = new Cart('shopware', 'existing');
        $cart->addLineItems(new LineItemCollection([
            new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex(), 1),
        ]));

        $connection->expects(static::once())
            ->method('delete')
            ->with('`cart`', ['token' => $cart->getToken()]);

        $persister->save(
            $cart,
            $this->getSalesChannelContext($cart->getToken())
        );

        static::assertInstanceOf(CartVerifyPersistEvent::class, $caughtEvent);
        static::assertFalse($caughtEvent->shouldBePersisted());
        static::assertCount(1, $caughtEvent->getCart()->getLineItems());

        $eventDispatcher->removeListener(CartVerifyPersistEvent::class, $handler);
    }

    private function getSalesChannelContext(string $token): SalesChannelContext
    {
        return $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create($token, Defaults::SALES_CHANNEL);
    }
}
