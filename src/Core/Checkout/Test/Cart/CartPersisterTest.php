<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CartPersisterTest extends TestCase
{
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

    public function testEmptyCartShouldnBeSaved(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();

        // Cart should be deleted (in case it exists).
        $connection->expects(static::once())->method('delete');

        // Cart should not be inserted or updated.
        $connection->expects(static::never())->method('executeUpdate');

        $persister = new CartPersister($connection, $eventDispatcher);

        $calc = new Cart('shopware', 'existing');

        $persister->save($calc, Generator::createSalesChannelContext());
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

        $calc = new Cart('shopware', 'existing');
        $calc->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister->save($calc, Generator::createSalesChannelContext());
    }

    public function testEventIsFired(): void
    {
        $connection = $this->createMock(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $connection->expects(static::once())->method('executeUpdate');

        $caughtEvent = null;
        $eventDispatcher->addListener(CartSavedEvent::class, static function (CartSavedEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $persister = new CartPersister($connection, $eventDispatcher);

        $calc = new Cart('shopware', 'existing');
        $calc->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister->save($calc, Generator::createSalesChannelContext());

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
}
