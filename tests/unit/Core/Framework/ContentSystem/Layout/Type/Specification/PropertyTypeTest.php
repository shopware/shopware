<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
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
        yield 'false for a string under number' => ['number', '3.5', false];
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

    #[TestDox('reports the nested member declarations of an object declaration through its schema')]
    public function testToSchemaReportsTheNestedMemberDeclarations(): void
    {
        $type = new PropertyType('object', false, null, null, [
            'url' => new PropertySpecification('url', new PropertyType('string', false, null, null), true, 'Url', 'Link target.', null),
        ]);

        static::assertSame([
            'url' => [
                'type' => 'string',
                'translatable' => false,
                'enum' => null,
                'default' => null,
                'properties' => null,
                'required' => true,
                'title' => 'Url',
                'description' => 'Link target.',
                'adminUI' => null,
            ],
        ], $type->toSchema()['properties']);
    }

    #[TestDox('reports the declared type, enum and default through its schema, with null where no nested members are declared')]
    public function testToSchemaReportsEveryDeclaredMember(): void
    {
        $type = new PropertyType('string', false, ['auto-fit', 'fixed'], 'auto-fit');

        static::assertSame([
            'type' => 'string',
            'translatable' => false,
            'enum' => ['auto-fit', 'fixed'],
            'default' => 'auto-fit',
            'properties' => null,
        ], $type->toSchema());
    }

    /**
     * @param string|list<string> $declaredType
     */
    #[DataProvider('primitiveTypeProvider')]
    #[TestDox('tells a lone primitive declaration from any other declared type: $_dataName')]
    public function testIsPrimitiveTellsALonePrimitiveFromAnyOtherDeclaredType(string|array $declaredType, bool $expected): void
    {
        static::assertSame($expected, (new PropertyType($declaredType, false, null, null))->isPrimitive());
    }

    /**
     * @return iterable<string, array{string|list<string>, bool}>
     */
    public static function primitiveTypeProvider(): iterable
    {
        yield 'lone primitive' => ['string', true];

        yield 'bare object' => ['object', false];

        yield 'FQCN' => [SalesChannelProductEntity::class, false];

        yield 'union of one primitive' => [['string'], false];
    }

    /**
     * @param string|list<string> $declared
     */
    private static function type(string|array $declared): PropertyType
    {
        return new PropertyType($declared, false, null, null);
    }
}
