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

        $result = self::unserialize($serialized);

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
        $result = self::unserialize($serialized);

        Assert::assertInstanceOf($class, $result);

        return $result;
    }

    /**
     * Unserializes a string, asserts the result is an array, and returns it —
     * useful when the serialized blob comes from an external source and the
     * caller needs to continue asserting individual keys or values.
     *
     * @return array<mixed>
     */
    public static function assertUnserializedIsArray(string $serialized): array
    {
        $result = self::unserialize($serialized);

        Assert::assertIsArray($result);

        return $result;
    }

    /**
     * Unserializes a string, asserts the result equals the expected value using
     * loose equality, and returns it — useful when comparing objects where
     * identity does not matter, only structural equality.
     */
    public static function assertUnserializedEquals(mixed $expected, string $serialized, string $message = ''): mixed
    {
        $result = self::unserialize($serialized);

        Assert::assertEquals($expected, $result, $message);

        return $result;
    }

    /**
     * @param scalar|array<mixed>|null $expected
     *
     * @return scalar|array<mixed>|null
     */
    public static function assertUnserializedSame(int|float|string|bool|array|null $expected, string $serialized, string $message = ''): int|float|string|bool|array|null
    {
        $result = self::unserialize($serialized, ['allowed_classes' => false]);

        Assert::assertSame($expected, $result, $message);

        return $result;
    }

    /**
     * unserialize() reports malformed input through a PHP warning instead of an exception. The warning is
     * turned into an assertion failure, so a test sees one failure with the parser message rather than a
     * warning plus a follow-up type assertion on `false`.
     *
     * @param array{allowed_classes?: bool|list<class-string>} $options
     */
    private static function unserialize(string $serialized, array $options = []): mixed
    {
        $error = null;
        set_error_handler(static function (int $errno, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $result = \unserialize($serialized, $options);
        } finally {
            restore_error_handler();
        }

        if ($error !== null) {
            Assert::fail(\sprintf('The string could not be unserialized: %s', $error));
        }

        return $result;
    }
}
