<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

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
        $persister = new CartPersister($connection, new CollectingEventDispatcher(), $cartSerializationCleaner, new CartCompressor(false, 'gzip'));
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

        $persister = new CartPersister($connection, new CollectingEventDispatcher(), $cartSerializationCleaner, $cartCompressor);

        $this->expectException(CartTokenNotFoundException::class);
        $persister->load('token', Generator::generateSalesChannelContext());
    }
}
