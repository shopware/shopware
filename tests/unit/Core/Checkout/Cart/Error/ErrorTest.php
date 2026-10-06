<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Tests\Unit\Core\Checkout\Cart\Error\_fixtures\SerializableTestError;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Error::class)]
class ErrorTest extends TestCase
{
    public function testSerializationKeepsTheStateOfTheConcreteError(): void
    {
        $error = new SerializableTestError('error-id', ['quantity' => 5]);

        $unserialized = Serialization::assertRoundTrip($error);

        static::assertSame('error-id', $unserialized->getId(), 'private property of the concrete error');
        static::assertSame(['quantity' => 5], $unserialized->getParameters(), 'protected property of the concrete error');
        static::assertSame('Serializable test error error-id', $unserialized->getMessage());
        static::assertSame(42, $unserialized->getCode());
        static::assertSame($error->getFile(), $unserialized->getFile());
        static::assertSame($error->getLine(), $unserialized->getLine());
    }

    public function testSerializedErrorKeepsItsBehaviour(): void
    {
        $unserialized = Serialization::assertRoundTrip(new SerializableTestError('error-id', []));

        static::assertSame('serializable-test-error', $unserialized->getMessageKey());
        static::assertSame(Error::LEVEL_WARNING, $unserialized->getLevel());
        static::assertTrue($unserialized->blockOrder());
        static::assertTrue($unserialized->blockResubmit());
        static::assertTrue($unserialized->isPersistent());
    }
}
