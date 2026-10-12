<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
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
    #[DataProvider('admittedValueProvider')]
    #[TestDox('admits a stored value conforming to the declared type: $_dataName')]
    public function testAdmitsConformingStoredValue(PropertyType $type, StoredValue $value): void
    {
        static::assertTrue($type->admits($value));
    }

    /**
     * @return iterable<string, array{PropertyType, StoredValue}>
     */
    public static function admittedValueProvider(): iterable
    {
        yield 'single-entry language map on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen')]),
        ];

        // Pairs with the rejected `wrong-primitive entry after a valid one` row: together they pin that the entry
        // loop judges every entry and admits on entry count alone. Without this row, a rejection keyed on
        // `count > 1` rather than on entry type passes the whole class.
        yield 'multi-entry language map on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofMap([
                Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen'),
                'language-de' => StoredValue::ofString('Welcome'),
            ]),
        ];

        yield 'language map holding an empty string entry on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('')]),
        ];

        // The entry type follows the declared primitive: the same integer entry the translatable string rejects.
        yield 'language map holding integer entries on a translatable integer' => [
            new PropertyType('integer', true, null, null),
            StoredValue::ofMap([
                Defaults::LANGUAGE_SYSTEM => StoredValue::ofInt(3),
                'language-de' => StoredValue::ofInt(0),
            ]),
        ];

        yield 'language map holding boolean entries on a translatable boolean' => [
            new PropertyType('boolean', true, null, null),
            StoredValue::ofMap([
                Defaults::LANGUAGE_SYSTEM => StoredValue::ofBool(false),
                'language-de' => StoredValue::ofBool(true),
            ]),
        ];

        // `number` admits an integer entry beside a float one, the same rule a non-translatable `number` applies.
        yield 'language map holding an integer and a float entry on a translatable number' => [
            new PropertyType('number', true, null, null),
            StoredValue::ofMap([
                Defaults::LANGUAGE_SYSTEM => StoredValue::ofInt(3),
                'language-de' => StoredValue::ofFloat(3.5),
            ]),
        ];

        // A bare `object`, an FQCN and a union carrying either constrain nothing, so a map stays admissible
        // there — the three declarations that keep an authored map legal outside a translatable property.
        yield 'map on a bare object declaration' => [
            new PropertyType('object', false, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen')]),
        ];

        yield 'map on an FQCN declaration' => [
            new PropertyType(SalesChannelProductEntity::class, false, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen')]),
        ];

        yield 'map on a mixed union declaration' => [
            new PropertyType(['string', 'object'], false, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen')]),
        ];

        yield 'map on an empty union declaration' => [
            new PropertyType([], false, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen')]),
        ];

        yield 'null on a non-translatable string' => [
            new PropertyType('string', false, null, null),
            StoredValue::ofNull(),
        ];

        yield 'string on a lone string declaration' => [
            new PropertyType('string', false, null, null),
            StoredValue::ofString('auto-fit'),
        ];

        // `number` admits an integer as well as a float, because JSON carries no distinction.
        yield 'integer on a lone number declaration' => [
            new PropertyType('number', false, null, null),
            StoredValue::ofInt(3),
        ];

        yield 'float on a lone number declaration' => [
            new PropertyType('number', false, null, null),
            StoredValue::ofFloat(3.5),
        ];

        yield 'integer on an all-primitive union carrying integer' => [
            new PropertyType(['string', 'integer'], false, null, null),
            StoredValue::ofInt(3),
        ];

        yield 'integer on a lone integer declaration' => [
            new PropertyType('integer', false, null, null),
            StoredValue::fromDecoded(42),
        ];

        yield 'boolean on a lone boolean declaration' => [
            new PropertyType('boolean', false, null, null),
            StoredValue::fromDecoded(true),
        ];

        // The null variant is admitted under a union too, not only under a lone primitive.
        yield 'null on an all-primitive union' => [
            new PropertyType(['string', 'integer'], false, null, null),
            StoredValue::fromDecoded(null),
        ];

        // A scalar is as unconstrained as a map under the declarations that enforce no primitive.
        yield 'integer on an FQCN declaration' => [
            new PropertyType(SalesChannelProductEntity::class, false, null, null),
            StoredValue::fromDecoded(42),
        ];

        yield 'integer on a mixed union declaration' => [
            new PropertyType(['string', 'object'], false, null, null),
            StoredValue::fromDecoded(42),
        ];
    }

    /**
     * @param list<string>|null $expected
     */
    #[DataProvider('enforceableTypesProvider')]
    #[TestDox('reports the primitives a value must satisfy, or null when the declaration constrains nothing: $_dataName')]
    public function testEnforceableTypesReportsThePrimitivesAValueMustSatisfy(PropertyType $type, ?array $expected): void
    {
        static::assertSame($expected, $type->enforceableTypes());
    }

    /**
     * @return iterable<string, array{PropertyType, list<string>|null}>
     */
    public static function enforceableTypesProvider(): iterable
    {
        yield 'lone primitive lists itself' => [
            new PropertyType('string', false, null, null),
            ['string'],
        ];

        yield 'all-primitive union lists its members in declared order' => [
            new PropertyType(['string', 'integer'], false, null, null),
            ['string', 'integer'],
        ];

        // One non-primitive member accepts every value, so the whole union constrains nothing.
        yield 'union carrying object constrains nothing' => [
            new PropertyType(['string', 'object'], false, null, null),
            null,
        ];

        yield 'bare object constrains nothing' => [
            new PropertyType('object', false, null, null),
            null,
        ];

        yield 'FQCN constrains nothing' => [
            new PropertyType(SalesChannelProductEntity::class, false, null, null),
            null,
        ];

        yield 'empty union constrains nothing' => [
            new PropertyType([], false, null, null),
            null,
        ];
    }

    #[DataProvider('storedDefaultProvider')]
    #[TestDox('resolves the declared default to its stored shape: $_dataName')]
    public function testStoredDefaultResolvesDeclaredDefaultToItsStoredShape(PropertyType $type, mixed $expected): void
    {
        static::assertSame($expected, $type->storedDefault());
    }

    /**
     * @return iterable<string, array{PropertyType, mixed}>
     */
    public static function storedDefaultProvider(): iterable
    {
        yield 'translatable string keys its default under the anchor language' => [
            new PropertyType('string', true, null, 'Willkommen'),
            [Defaults::LANGUAGE_SYSTEM => 'Willkommen'],
        ];

        // A false default still seeds the anchor entry: the null test is an identity check on the translatable
        // branch too.
        yield 'translatable boolean keys a false default under the anchor language' => [
            new PropertyType('boolean', true, null, false),
            [Defaults::LANGUAGE_SYSTEM => false],
        ];

        yield 'non-translatable string keeps the bare scalar' => [
            new PropertyType('string', false, null, 'auto-fit'),
            'auto-fit',
        ];

        // A false default is not an absent default: the null test has to be an identity check, not truthiness.
        yield 'non-translatable boolean keeps a false default' => [
            new PropertyType('boolean', false, null, false),
            false,
        ];

        yield 'translatable string without a declared default seeds nothing' => [
            new PropertyType('string', true, null, null),
            null,
        ];

        yield 'non-translatable string without a declared default seeds nothing' => [
            new PropertyType('string', false, null, null),
            null,
        ];
    }

    #[DataProvider('inStoredShapeProvider')]
    #[TestDox('puts a value into the shape storage holds for the property: $_dataName')]
    public function testInStoredShapePutsValueIntoItsStoredShape(PropertyType $type, mixed $value, mixed $expected): void
    {
        static::assertSame($expected, $type->inStoredShape($value));
    }

    /**
     * @return iterable<string, array{PropertyType, mixed, mixed}>
     */
    public static function inStoredShapeProvider(): iterable
    {
        yield 'translatable string is wrapped under the anchor language' => [
            new PropertyType('string', true, null, null),
            'Autumn sale',
            [Defaults::LANGUAGE_SYSTEM => 'Autumn sale'],
        ];

        yield 'non-translatable string stays bare' => [
            new PropertyType('string', false, null, null),
            'Autumn sale',
            'Autumn sale',
        ];

        // Null is no language-map entry, so it is returned as is for the caller's own validation to reject.
        yield 'null on a translatable string stays null' => [
            new PropertyType('string', true, null, null),
            null,
            null,
        ];

        // Falsy is not absent: the null test is an identity check, so false is wrapped.
        yield 'false on a translatable boolean is wrapped under the anchor language' => [
            new PropertyType('boolean', true, null, null),
            false,
            [Defaults::LANGUAGE_SYSTEM => false],
        ];
    }

    #[DataProvider('describedTypeProvider')]
    #[TestDox('renders the declared type as a violation message names it: $_dataName')]
    public function testDescribeRendersTheDeclaredType(PropertyType $type, string $expected): void
    {
        static::assertSame($expected, $type->describe());
    }

    /**
     * @return iterable<string, array{PropertyType, string}>
     */
    public static function describedTypeProvider(): iterable
    {
        yield 'lone scalar' => [
            new PropertyType('string', false, null, null),
            'string',
        ];

        yield 'union renders its members pipe-separated' => [
            new PropertyType(['integer', 'string'], false, null, null),
            'integer|string',
        ];

        // The flag is what separates the two `string` declarations a client must tell apart.
        yield 'translatable string spells out the flag' => [
            new PropertyType('string', true, null, null),
            'string (translatable)',
        ];
    }

    #[DataProvider('admittedMapEntryProvider')]
    #[TestDox('admits a language-map entry matching the declared primitive: $_dataName')]
    public function testAdmitsMapEntryMatchingTheDeclaredPrimitive(PropertyType $type, StoredValue $entry): void
    {
        static::assertTrue($type->admitsMapEntry($entry));
    }

    /**
     * @return iterable<string, array{PropertyType, StoredValue}>
     */
    public static function admittedMapEntryProvider(): iterable
    {
        yield 'string entry on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofString('Willkommen'),
        ];

        yield 'false entry on a translatable boolean' => [
            new PropertyType('boolean', true, null, null),
            StoredValue::ofBool(false),
        ];

        yield 'integer entry on a translatable number' => [
            new PropertyType('number', true, null, null),
            StoredValue::ofInt(3),
        ];
    }

    #[DataProvider('rejectedMapEntryProvider')]
    #[TestDox('refuses a language-map entry the declaration cannot hold: $_dataName')]
    public function testRefusesMapEntryTheDeclarationCannotHold(PropertyType $type, StoredValue $entry): void
    {
        static::assertFalse($type->admitsMapEntry($entry));
    }

    /**
     * The first two rows refuse on the entry. The rest carry a string entry a translatable string admits, so the
     * declaration alone decides their refusal.
     *
     * @return iterable<string, array{PropertyType, StoredValue}>
     */
    public static function rejectedMapEntryProvider(): iterable
    {
        yield 'wrong-primitive entry on a translatable integer' => [
            new PropertyType('integer', true, null, null),
            StoredValue::ofString('3'),
        ];

        yield 'null entry on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofNull(),
        ];

        yield 'string entry on a translatable single-member union' => [
            new PropertyType(['string'], true, null, null),
            StoredValue::ofString('Willkommen'),
        ];

        yield 'string entry on a translatable FQCN declaration' => [
            new PropertyType(SalesChannelProductEntity::class, true, null, null),
            StoredValue::ofString('Willkommen'),
        ];

        yield 'string entry on a translatable bare object declaration' => [
            new PropertyType('object', true, null, null),
            StoredValue::ofString('Willkommen'),
        ];

        yield 'string entry on a non-translatable string' => [
            new PropertyType('string', false, null, null),
            StoredValue::ofString('Willkommen'),
        ];
    }

    #[TestDox('lists the keys of a translatable language map as strings, an integer key cast, in map order')]
    public function testLanguageKeysListsTheMapKeysAsStrings(): void
    {
        $type = new PropertyType('string', true, null, null);
        $map = StoredValue::ofMap([
            Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen'),
            42 => StoredValue::ofString('Welcome'),
        ]);

        static::assertSame([Defaults::LANGUAGE_SYSTEM, '42'], $type->languageKeys($map));
    }

    #[TestDox('lists no language keys for a map under a non-translatable declaration')]
    public function testLanguageKeysIsEmptyForANonTranslatableDeclaration(): void
    {
        $type = new PropertyType('string', false, null, null);
        $map = StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen')]);

        static::assertSame([], $type->languageKeys($map));
    }

    #[DataProvider('nonMapValueProvider')]
    #[TestDox('lists no language keys for a translatable declaration holding $_dataName')]
    public function testLanguageKeysIsEmptyForAValueThatIsNotAMap(StoredValue $value): void
    {
        static::assertSame([], (new PropertyType('string', true, null, null))->languageKeys($value));
    }

    /**
     * @return iterable<string, array{StoredValue}>
     */
    public static function nonMapValueProvider(): iterable
    {
        yield 'a bare string' => [StoredValue::ofString('Willkommen')];

        yield 'a list' => [StoredValue::ofList([StoredValue::ofString('Willkommen')])];

        yield 'the null variant' => [StoredValue::ofNull()];
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

    #[DataProvider('translatableFlagProvider')]
    #[TestDox('reports the declared translatable flag through its schema: $_dataName')]
    public function testToSchemaReportsTheDeclaredTranslatableFlag(bool $translatable): void
    {
        $type = new PropertyType('string', $translatable, null, null);

        static::assertSame($translatable, $type->toSchema()['translatable']);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function translatableFlagProvider(): iterable
    {
        yield 'translatable declaration' => [true];

        yield 'non-translatable declaration' => [false];
    }

    #[DataProvider('rejectedValueProvider')]
    #[TestDox('rejects a stored value that does not conform to the declared type: $_dataName')]
    public function testRejectsNonConformingStoredValue(PropertyType $type, StoredValue $value): void
    {
        static::assertFalse($type->admits($value));
    }

    /**
     * @return iterable<string, array{PropertyType, StoredValue}>
     */
    public static function rejectedValueProvider(): iterable
    {
        // The wire shape of "an empty map": `[]` decodes to the (empty) list variant — the map variant itself
        // cannot be empty ({@see StoredValue::ofMap()}) — and no translations is the key being absent.
        yield 'empty array on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::fromDecoded([]),
        ];

        yield 'bare string on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofString('Willkommen'),
        ];

        yield 'null on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofNull(),
        ];

        yield 'list on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofList([StoredValue::ofString('Willkommen')]),
        ];

        yield 'language map holding a null entry' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofNull()]),
        ];

        // Declared-type-dependent: the translatable integer admits this entry.
        yield 'language map holding an integer entry on a translatable string' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofInt(3)]),
        ];

        // `integer` admits no float, inside a language map as outside one.
        yield 'language map holding a float entry on a translatable integer' => [
            new PropertyType('integer', true, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofFloat(3.5)]),
        ];

        yield 'language map holding a string entry on a translatable boolean' => [
            new PropertyType('boolean', true, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('true')]),
        ];

        yield 'language map holding a boolean entry on a translatable number' => [
            new PropertyType('number', true, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofBool(true)]),
        ];

        yield 'bare boolean on a translatable boolean' => [
            new PropertyType('boolean', true, null, null),
            StoredValue::ofBool(false),
        ];

        // The only row whose first entry is a valid string: it pins that the entry loop judges every entry
        // rather than the first one, which every other rejected map row would still admit.
        yield 'language map holding a wrong-primitive entry after a valid one' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofMap([
                Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen'),
                'language-de' => StoredValue::ofInt(3),
            ]),
        ];

        yield 'language map holding a nested map entry' => [
            new PropertyType('string', true, null, null),
            StoredValue::ofMap([
                Defaults::LANGUAGE_SYSTEM => StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen')]),
            ]),
        ];

        yield 'map on a non-translatable lone string' => [
            new PropertyType('string', false, null, null),
            StoredValue::ofMap([Defaults::LANGUAGE_SYSTEM => StoredValue::ofString('Willkommen')]),
        ];

        yield 'integer on a lone string declaration' => [
            new PropertyType('string', false, null, null),
            StoredValue::ofInt(3),
        ];

        yield 'list on a lone string declaration' => [
            new PropertyType('string', false, null, null),
            StoredValue::fromDecoded(['a']),
        ];

        yield 'string on a lone boolean declaration' => [
            new PropertyType('boolean', false, null, null),
            StoredValue::ofString('true'),
        ];

        yield 'float on a lone integer declaration' => [
            new PropertyType('integer', false, null, null),
            StoredValue::ofFloat(3.5),
        ];

        yield 'string on a lone number declaration' => [
            new PropertyType('number', false, null, null),
            StoredValue::ofString('3.5'),
        ];

        yield 'boolean on an all-primitive union of string and integer' => [
            new PropertyType(['string', 'integer'], false, null, null),
            StoredValue::ofBool(true),
        ];
    }
}
