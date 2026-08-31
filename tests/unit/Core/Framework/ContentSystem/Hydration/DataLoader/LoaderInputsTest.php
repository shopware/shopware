<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoaderInputs::class)]
class LoaderInputsTest extends TestCase
{
    /**
     * The declared keys of the fixture below, in the order the map carries them: the exception message for an
     * undeclared read lists them verbatim.
     */
    private const DECLARED_KEYS = ['entityId', 'limit', 'enabled', 'associations', 'unresolved', 'emptyString', 'emptyList', 'map', 'mixedList'];

    #[DataProvider('hasProvider')]
    #[TestDox('has() reports a declared key resolved only when it is non-null: $_dataName')]
    public function testHasReportsResolvedKeysOnly(string $key, bool $expected): void
    {
        static::assertSame($expected, self::inputs()->has($key));
    }

    #[DataProvider('getProvider')]
    #[TestDox('get() returns the raw resolved value, null when unresolved: $_dataName')]
    public function testGetReturnsRawValue(string $key, mixed $expected): void
    {
        static::assertSame($expected, self::inputs()->get($key));
    }

    /**
     * @param \Closure(LoaderInputs): mixed $read
     */
    #[DataProvider('resolvedAccessorProvider')]
    #[TestDox('typed accessor returns the resolved value: $_dataName')]
    public function testTypedAccessorReturnsResolvedValue(\Closure $read, mixed $expected): void
    {
        static::assertSame($expected, $read(self::inputs()));
    }

    /**
     * @param \Closure(LoaderInputs): mixed $read
     */
    #[DataProvider('nullableAccessorResolvedProvider')]
    #[TestDox('nullable accessor returns the resolved value: $_dataName')]
    public function testNullableAccessorReturnsResolvedValue(\Closure $read, mixed $expected): void
    {
        static::assertSame($expected, $read(self::inputs()));
    }

    /**
     * @param \Closure(LoaderInputs): mixed $read
     */
    #[DataProvider('nullableAccessorProvider')]
    #[TestDox('nullable accessor returns null when the input is unresolved: $_dataName')]
    public function testNullableAccessorReturnsNullWhenUnresolved(\Closure $read): void
    {
        static::assertNull($read(self::inputs()));
    }

    #[TestDox('has() throws for a key the loader never declared')]
    public function testHasThrowsForUndeclaredKey(): void
    {
        $this->expectExceptionObject(ContentSystemException::loaderInputNotDeclared('rootId', self::DECLARED_KEYS));

        self::inputs()->has('rootId');
    }

    #[TestDox('get() throws for a key the loader never declared')]
    public function testGetThrowsForUndeclaredKey(): void
    {
        $this->expectExceptionObject(ContentSystemException::loaderInputNotDeclared('rootId', self::DECLARED_KEYS));

        self::inputs()->get('rootId');
    }

    /**
     * @param \Closure(LoaderInputs): mixed $read
     */
    #[DataProvider('nonNullableAccessorProvider')]
    #[TestDox('non-nullable accessor throws when the input is unresolved: $_dataName')]
    public function testNonNullableAccessorThrowsWhenUnresolved(\Closure $read): void
    {
        $this->expectExceptionObject(ContentSystemException::loaderInputUnresolved('unresolved'));

        $read(self::inputs());
    }

    /**
     * @param \Closure(LoaderInputs): mixed $read
     */
    #[DataProvider('typeMismatchProvider')]
    #[TestDox('accessor throws when the resolved value has another type: $_dataName')]
    public function testAccessorThrowsOnTypeMismatch(\Closure $read, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $read(self::inputs());
    }

    /**
     * @param \Closure(LoaderInputs): mixed $read
     */
    #[DataProvider('undeclaredAccessorProvider')]
    #[TestDox('accessor throws for a key the loader never declared: $_dataName')]
    public function testAccessorThrowsForUndeclaredKey(\Closure $read): void
    {
        $this->expectExceptionObject(ContentSystemException::loaderInputNotDeclared('rootId', self::DECLARED_KEYS));

        $read(self::inputs());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function hasProvider(): iterable
    {
        yield 'declared and resolved' => ['entityId', true];
        yield 'declared but unresolved' => ['unresolved', false];
        yield 'resolved to an empty string' => ['emptyString', true];
        yield 'resolved to an empty list' => ['emptyList', true];
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function getProvider(): iterable
    {
        yield 'resolved' => ['entityId', 'product-alice'];
        yield 'unresolved' => ['unresolved', null];
    }

    /**
     * @return iterable<string, array{\Closure(LoaderInputs): mixed, mixed}>
     */
    public static function resolvedAccessorProvider(): iterable
    {
        yield 'string()' => [static fn (LoaderInputs $inputs): string => $inputs->string('entityId'), 'product-alice'];
        yield 'int()' => [static fn (LoaderInputs $inputs): int => $inputs->int('limit'), 5];
        yield 'bool()' => [static fn (LoaderInputs $inputs): bool => $inputs->bool('enabled'), true];
        yield 'stringList()' => [static fn (LoaderInputs $inputs): array => $inputs->stringList('associations'), ['media', 'cover']];
    }

    /**
     * @return iterable<string, array{\Closure(LoaderInputs): mixed}>
     */
    public static function nonNullableAccessorProvider(): iterable
    {
        yield 'string()' => [static fn (LoaderInputs $inputs): string => $inputs->string('unresolved')];
        yield 'int()' => [static fn (LoaderInputs $inputs): int => $inputs->int('unresolved')];
        yield 'bool()' => [static fn (LoaderInputs $inputs): bool => $inputs->bool('unresolved')];
        yield 'stringList()' => [static fn (LoaderInputs $inputs): array => $inputs->stringList('unresolved')];
    }

    /**
     * @return iterable<string, array{\Closure(LoaderInputs): mixed}>
     */
    public static function nullableAccessorProvider(): iterable
    {
        yield 'stringOrNull()' => [static fn (LoaderInputs $inputs): ?string => $inputs->stringOrNull('unresolved')];
        yield 'intOrNull()' => [static fn (LoaderInputs $inputs): ?int => $inputs->intOrNull('unresolved')];
        yield 'stringListOrNull()' => [static fn (LoaderInputs $inputs): ?array => $inputs->stringListOrNull('unresolved')];
    }

    /**
     * @return iterable<string, array{\Closure(LoaderInputs): mixed, mixed}>
     */
    public static function nullableAccessorResolvedProvider(): iterable
    {
        yield 'stringOrNull()' => [static fn (LoaderInputs $inputs): ?string => $inputs->stringOrNull('entityId'), 'product-alice'];
        yield 'intOrNull()' => [static fn (LoaderInputs $inputs): ?int => $inputs->intOrNull('limit'), 5];
        yield 'stringListOrNull()' => [static fn (LoaderInputs $inputs): ?array => $inputs->stringListOrNull('associations'), ['media', 'cover']];
        yield 'stringOrNull() on an empty string' => [static fn (LoaderInputs $inputs): ?string => $inputs->stringOrNull('emptyString'), ''];
    }

    /**
     * @return iterable<string, array{\Closure(LoaderInputs): mixed, ContentSystemException}>
     */
    public static function typeMismatchProvider(): iterable
    {
        yield 'string() on an int' => [
            static fn (LoaderInputs $inputs): string => $inputs->string('limit'),
            ContentSystemException::loaderInputTypeMismatch('limit', 'string', 'int'),
        ];

        yield 'int() on a string' => [
            static fn (LoaderInputs $inputs): int => $inputs->int('entityId'),
            ContentSystemException::loaderInputTypeMismatch('entityId', 'int', 'string'),
        ];

        yield 'bool() on a string' => [
            static fn (LoaderInputs $inputs): bool => $inputs->bool('entityId'),
            ContentSystemException::loaderInputTypeMismatch('entityId', 'bool', 'string'),
        ];

        yield 'stringList() on a string' => [
            static fn (LoaderInputs $inputs): array => $inputs->stringList('entityId'),
            ContentSystemException::loaderInputTypeMismatch('entityId', 'list<string>', 'string'),
        ];

        yield 'stringList() on a map' => [
            static fn (LoaderInputs $inputs): array => $inputs->stringList('map'),
            ContentSystemException::loaderInputTypeMismatch('map', 'list<string>', 'array'),
        ];

        yield 'stringList() on a list with a non-string entry' => [
            static fn (LoaderInputs $inputs): array => $inputs->stringList('mixedList'),
            ContentSystemException::loaderInputTypeMismatch('mixedList', 'list<string>', 'array'),
        ];

        yield 'stringOrNull() on an int' => [
            static fn (LoaderInputs $inputs): ?string => $inputs->stringOrNull('limit'),
            ContentSystemException::loaderInputTypeMismatch('limit', 'string', 'int'),
        ];

        yield 'intOrNull() on a string' => [
            static fn (LoaderInputs $inputs): ?int => $inputs->intOrNull('entityId'),
            ContentSystemException::loaderInputTypeMismatch('entityId', 'int', 'string'),
        ];

        yield 'stringListOrNull() on a string' => [
            static fn (LoaderInputs $inputs): ?array => $inputs->stringListOrNull('entityId'),
            ContentSystemException::loaderInputTypeMismatch('entityId', 'list<string>', 'string'),
        ];
    }

    /**
     * @return iterable<string, array{\Closure(LoaderInputs): mixed}>
     */
    public static function undeclaredAccessorProvider(): iterable
    {
        yield 'string()' => [static fn (LoaderInputs $inputs): string => $inputs->string('rootId')];
        yield 'int()' => [static fn (LoaderInputs $inputs): int => $inputs->int('rootId')];
        yield 'bool()' => [static fn (LoaderInputs $inputs): bool => $inputs->bool('rootId')];
        yield 'stringList()' => [static fn (LoaderInputs $inputs): array => $inputs->stringList('rootId')];
        yield 'stringOrNull()' => [static fn (LoaderInputs $inputs): ?string => $inputs->stringOrNull('rootId')];
        yield 'stringListOrNull()' => [static fn (LoaderInputs $inputs): ?array => $inputs->stringListOrNull('rootId')];
    }

    private static function inputs(): LoaderInputs
    {
        return new LoaderInputs([
            'entityId' => 'product-alice',
            'limit' => 5,
            'enabled' => true,
            'associations' => ['media', 'cover'],
            'unresolved' => null,
            'emptyString' => '',
            'emptyList' => [],
            'map' => ['color' => 'red'],
            'mixedList' => ['media', 42],
        ]);
    }
}
