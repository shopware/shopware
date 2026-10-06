<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigKeySpecification::class)]
class ConfigKeySpecificationTest extends TestCase
{
    #[DataProvider('admitsReferencedValueProvider')]
    #[TestDox('judges a referenced value: $_dataName')]
    public function testAdmitsReferencedValue(string $referencedType, mixed $value, bool $expected): void
    {
        $key = new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', true, referencedType: $referencedType);

        static::assertSame($expected, $key->admitsReferencedValue($value));
    }

    /**
     * @return iterable<string, array{string, mixed, bool}>
     */
    public static function admitsReferencedValueProvider(): iterable
    {
        yield 'a string key admits a string' => ['string', 'a-product-id', true];
        yield 'a string key admits an empty string' => ['string', '', true];
        yield 'a string key rejects a list of strings' => ['string', ['a', 'b'], false];
        yield 'a string key rejects a non-list array' => ['string', ['first' => 'a'], false];
        yield 'a string key rejects an integer' => ['string', 42, false];
        yield 'a string key rejects a boolean' => ['string', true, false];
        yield 'a string key rejects null' => ['string', null, false];
        yield 'a list<string> key rejects a string' => ['list<string>', 'a', false];
        yield 'a list<string> key admits a list of strings' => ['list<string>', ['a', 'b'], true];
        yield 'a list<string> key admits an empty list' => ['list<string>', [], true];
        yield 'a list<string> key rejects a list carrying a non-string entry' => ['list<string>', ['a', 42], false];
        yield 'a list<string> key rejects a non-list array of strings' => ['list<string>', ['first' => 'a', 'second' => 'b'], false];
        yield 'a list<string> key rejects an integer' => ['list<string>', 42, false];
        yield 'a list<string> key rejects a boolean' => ['list<string>', false, false];
        yield 'a list<string> key rejects null' => ['list<string>', null, false];
        // Outside REFERENCED_TYPES, which the container build rejects; a key constructed with one admits nothing.
        yield 'an undeclarable referenced type rejects even a value of its own shape' => ['list<integer>', [1, 2], false];
    }

    #[DataProvider('admitsDeclaredPrimitiveProvider')]
    #[TestDox('judges a declared primitive: $_dataName')]
    public function testAdmitsDeclaredPrimitive(string $referencedType, string $primitive, bool $expected): void
    {
        $key = new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', true, referencedType: $referencedType);

        static::assertSame($expected, $key->admitsDeclaredPrimitive($primitive));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function admitsDeclaredPrimitiveProvider(): iterable
    {
        yield 'a string key admits a string declaration' => ['string', 'string', true];
        yield 'a string key rejects an integer declaration' => ['string', 'integer', false];
        yield 'a string key rejects a number declaration' => ['string', 'number', false];
        yield 'a string key rejects a boolean declaration' => ['string', 'boolean', false];
        yield 'a list<string> key rejects a string declaration' => ['list<string>', 'string', false];
        yield 'a list<string> key rejects an integer declaration' => ['list<string>', 'integer', false];
        yield 'a list<string> key rejects a number declaration' => ['list<string>', 'number', false];
        yield 'a list<string> key rejects a boolean declaration' => ['list<string>', 'boolean', false];
    }
}
