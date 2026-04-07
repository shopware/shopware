<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Assert;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Assert\Serialization;

/**
 * @internal
 */
#[CoversClass(Serialization::class)]
class SerializationTest extends TestCase
{
    public function testAssertRoundTripReturnsUnserialized(): void
    {
        $original = new \stdClass();
        $original->value = 'hello';

        $result = Serialization::assertRoundTrip($original);

        static::assertInstanceOf(\stdClass::class, $result);
        static::assertSame('hello', $result->value);
    }

    public function testAssertRoundTripPreservesType(): void
    {
        $original = new \ArrayObject(['a', 'b', 'c']);

        $result = Serialization::assertRoundTrip($original);

        static::assertInstanceOf(\ArrayObject::class, $result);
        static::assertSame(['a', 'b', 'c'], $result->getArrayCopy());
    }

    public function testAssertRoundTripFailsWhenClassChangesOnUnserialize(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Deserialization not allowed'));

        Serialization::assertRoundTrip(new UnserializableStub());
    }

    public function testAssertDeserializesReturnsInstance(): void
    {
        $original = new \ArrayObject([1, 2, 3]);
        $serialized = \serialize($original);

        $result = Serialization::assertUnserializedInstanceOf(\ArrayObject::class, $serialized);

        static::assertInstanceOf(\ArrayObject::class, $result);
        static::assertSame([1, 2, 3], $result->getArrayCopy());
    }

    public function testAssertDeserializesFailsOnWrongClass(): void
    {
        $serialized = \serialize(new \ArrayObject());

        $this->expectException(AssertionFailedError::class);

        Serialization::assertUnserializedInstanceOf(\stdClass::class, $serialized);
    }

    public function testAssertDeserializesFailsOnInvalidSerializedString(): void
    {
        $this->expectException(AssertionFailedError::class);

        Serialization::assertUnserializedInstanceOf(\stdClass::class, 'not-a-valid-serialized-string');
    }

    public function testAssertUnserializedSamePassesForString(): void
    {
        Serialization::assertUnserializedSame('hello', \serialize('hello'));
    }

    public function testAssertUnserializedSamePassesForInt(): void
    {
        Serialization::assertUnserializedSame(42, \serialize(42));
    }

    public function testAssertUnserializedSameFailsOnMismatch(): void
    {
        $this->expectException(AssertionFailedError::class);

        Serialization::assertUnserializedSame('expected', \serialize('actual'));
    }

    public function testAssertUnserializedSameFailsOnInvalidSerializedString(): void
    {
        $this->expectException(AssertionFailedError::class);

        Serialization::assertUnserializedSame('hello', 'not-a-valid-serialized-string');
    }
}

/**
 * @internal
 */
class UnserializableStub
{
    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new \RuntimeException('Deserialization not allowed');
    }
}
