<?php declare(strict_types=1);

namespace Shopware\Core\Test\Assert;

use PHPUnit\Framework\Assert;

/**
 * @internal
 */
final class Serialization
{
    /**
     * Serializes the given object, unserializes it, asserts the result is the
     * same type, and returns it — so the caller can continue asserting state.
     *
     * @template T of object
     *
     * @param T $object
     *
     * @return T
     */
    public static function assertRoundTrip(object $object): object
    {
        $serialized = \serialize($object);

        /** @phpstan-ignore shopware.unserializeUsage */
        $result = \unserialize($serialized);

        Assert::assertInstanceOf($object::class, $result);

        /** @var T $result */
        return $result;
    }

    /**
     * Unserializes a string and asserts the result is an instance of the given
     * class, then returns it typed — useful for testing persistence or cache
     * round trips where the serialized blob comes from an external source.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function assertUnserializedInstanceOf(string $class, string $serialized): object
    {
        /** @phpstan-ignore shopware.unserializeUsage */
        $result = \unserialize($serialized);

        Assert::assertInstanceOf($class, $result);

        return $result;
    }

    /**
     * @param scalar|array<mixed>|null $expected
     *
     * @return scalar|array<mixed>|null
     */
    public static function assertUnserializedSame(int|float|string|bool|array|null $expected, string $serialized, string $message = ''): int|float|string|bool|array|null
    {
        /** @phpstan-ignore shopware.unserializeUsage */
        $result = \unserialize($serialized);

        Assert::assertSame($expected, $result, $message);

        return $result;
    }
}
