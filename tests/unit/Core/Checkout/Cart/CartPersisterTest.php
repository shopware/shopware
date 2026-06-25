<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(CartPersister::class)]
#[Package('checkout')]
class CartPersisterTest extends TestCase
{
    public function testDecorated(): void
    {
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $connection = $this->createMock(Connection::class);
        $persister = new CartPersister($connection, new CollectingEventDispatcher(), $cartSerializationCleaner, new CartCompressor(false, 'gzip'), new NativeClock());
        $this->expectException(DecorationPatternException::class);
        $persister->getDecorated();
    }

    public function testLoadWithUnserializationTypeError(): void
    {
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['payload' => 'invalid serialized data', 'rule_ids' => null, 'compressed' => 0]);

        $cartCompressor = $this->createMock(CartCompressor::class);
        $cartCompressor->expects($this->once())
            ->method('unserialize')
            ->with('invalid serialized data', 0)
            ->willThrowException(new \TypeError('Unserialization failed'));

        $persister = new CartPersister($connection, new CollectingEventDispatcher(), $cartSerializationCleaner, $cartCompressor, new NativeClock());

        $this->expectException(CartTokenNotFoundException::class);
        $persister->load('token', Generator::generateSalesChannelContext());
    }

    public function testSavePersistsNewCartAndDispatchesSavedEvent(): void
    {
        $cart = new Cart('token');
        $cart->add(new LineItem('line-item', 'test'));

        $statement = $this->createMock(Statement::class);
        $statement->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('prepare')
            ->with(static::stringContains('INSERT INTO `cart`'))
            ->willReturn($statement);

        $eventDispatcher = new CollectingEventDispatcher();
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), new NativeClock());

        $persister->save($cart, Generator::generateSalesChannelContext());

        static::assertTrue($cart->isPersisted());
        static::assertCount(2, $eventDispatcher->getEvents());
        static::assertInstanceOf(CartVerifyPersistEvent::class, $eventDispatcher->getEvents()[0]);
        static::assertInstanceOf(CartSavedEvent::class, $eventDispatcher->getEvents()[1]);
    }

    public function testSaveDoesNotDispatchSavedEventWhenPersistedCartUpdateAffectsZeroRows(): void
    {
        $cart = new Cart('token');
        $cart->add(new LineItem('line-item', 'test'));
        $cart->setPersisted(true);

        $statement = $this->createMock(Statement::class);
        $statement->expects($this->once())
            ->method('executeStatement')
            ->willReturn(0);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('prepare')
            ->with(static::stringContains('UPDATE `cart`'))
            ->willReturn($statement);

        $eventDispatcher = new CollectingEventDispatcher();
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), new NativeClock());

        $persister->save($cart, Generator::generateSalesChannelContext());

        static::assertTrue($cart->isPersisted());
        static::assertCount(1, $eventDispatcher->getEvents());
        static::assertContainsOnlyInstancesOf(CartVerifyPersistEvent::class, $eventDispatcher->getEvents());
    }
}
