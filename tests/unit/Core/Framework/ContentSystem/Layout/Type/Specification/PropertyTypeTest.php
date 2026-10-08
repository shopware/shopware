<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PropertyType::class)]
class PropertyTypeTest extends TestCase
{
    /**
     * @param string|list<string> $declared
     * @param list<string>|null $expected
     */
    #[DataProvider('enforceableTypesProvider')]
    #[TestDox('enforceableTypes() answers $_dataName')]
    public function testEnforceableTypes(string|array $declared, ?array $expected): void
    {
        static::assertSame($expected, self::type($declared)->enforceableTypes());
    }

    /**
     * @param string|list<string> $declared
     */
    #[DataProvider('admitsProvider')]
    #[TestDox('admits() answers $_dataName')]
    public function testAdmits(string|array $declared, mixed $value, bool $expected): void
    {
        static::assertSame($expected, self::type($declared)->admits($value));
    }

    /**
     * @return iterable<string, array{string|list<string>, list<string>|null}>
     */
    public static function enforceableTypesProvider(): iterable
    {
        yield 'the single type for a primitive' => ['string', ['string']];
        yield 'null for a bare object' => ['object', null];
        yield 'null for an FQCN' => ['Shopware\Core\Content\Product\ProductEntity', null];
        yield 'every member of an all-primitive union' => [['string', 'integer'], ['string', 'integer']];
        yield 'null for a union carrying a non-primitive' => [['string', 'object'], null];
        yield 'null for an empty union' => [[], null];
    }

    /**
     * @return iterable<string, array{string|list<string>, mixed, bool}>
     */
    public static function admitsProvider(): iterable
    {
        yield 'true for a string under string' => ['string', 'hello', true];
        yield 'false for an integer under string' => ['string', 42, false];
        yield 'true for an integer under integer' => ['integer', 42, true];
        yield 'false for a float under integer' => ['integer', 4.2, false];
        yield 'true for an integer under number' => ['number', 42, true];
        yield 'true for a float under number' => ['number', 4.2, true];
        yield 'true for a bool under boolean' => ['boolean', true, true];
        yield 'false for an array under a primitive' => ['string', ['a'], false];

        yield 'true for a null under a primitive' => ['string', null, true];
        yield 'true for a null under a union' => [['string', 'integer'], null, true];

        yield 'true for a member match in a union' => [['string', 'integer'], 42, true];
        yield 'false for no member match in a union' => [['string', 'integer'], true, false];

        yield 'true for anything under a bare object' => ['object', ['whatever' => 1], true];
        yield 'true for anything under an FQCN' => ['Shopware\Core\Content\Product\ProductEntity', 42, true];
        yield 'true for anything under a union carrying a non-primitive' => [['string', 'object'], 42, true];
    }

    /**
     * @param string|list<string> $type
     * @param list<string> $expected
     */
    #[DataProvider('contextTypeProvider')]
    #[TestDox('reads $_dataName as accepting $expected')]
    public function testContextTypes(string|array $type, array $expected): void
    {
        static::assertSame($expected, (new PropertyType($type, false, null, null))->contextTypes());
    }

    /**
     * @return iterable<string, array{string|list<string>, list<string>}>
     */
    public static function contextTypeProvider(): iterable
    {
        yield 'a string property' => ['string', ['single']];
        yield 'an integer property' => ['integer', ['single']];
        yield 'a boolean property' => ['boolean', ['single']];
        yield 'a number property' => ['number', ['single']];

        // The distinction the Administration cannot make for itself, and the reason this method exists: the
        // gallery declares the collection and the image element the entity, and offering a candidate across
        // that line produces an element that renders nothing.
        yield 'an entity property' => [MediaEntity::class, ['single']];
        yield 'a collection property' => [MediaCollection::class, ['collection']];

        // Bare `object` names an object without naming which one, so it rules nothing out — matching how
        // MappingTypeCompatibility::permits() takes any class-typed candidate for it.
        yield 'an unconstrained object property' => ['object', ['single', 'collection']];

        yield 'a union of primitives' => [['string', 'integer'], ['single']];
        yield 'a union of an entity and a collection' => [
            [MediaEntity::class, MediaCollection::class],
            ['single', 'collection'],
        ];

        // A union repeating a kind reports it once, so the Administration's includes() check stays a plain
        // membership test rather than having to care how often a kind appears.
        yield 'a union of two collections' => [[MediaCollection::class, MediaCollection::class], ['collection']];
    }

    /**
     * The schema is what the Administration actually reads, so the derivation reaching it is the part that
     * matters; a correct contextTypes() that toSchema() drops would leave the selection UI exactly as broken.
     */
    #[TestDox('publishes the accepted context types in the schema')]
    public function testToSchemaCarriesContextTypes(): void
    {
        $schema = (new PropertyType(MediaCollection::class, false, null, null))->toSchema();

        static::assertSame(['collection'], $schema['contextTypes']);
    }

    /**
     * @param string|list<string> $declared
     */
    private static function type(string|array $declared): PropertyType
    {
        return new PropertyType($declared, false, null, null);
    }
}
