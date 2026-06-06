<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\CustomFieldRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CustomFieldRule::class)]
#[Group('rules')]
class CustomFieldRuleTest extends TestCase
{
    private const CUSTOM_FIELD_NAME = 'custom_test';

    public function testGetConstraints(): void
    {
        $ruleConstraints = CustomFieldRule::getConstraints([]);

        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints);
        static::assertEquals([new NotBlank()], $ruleConstraints['renderedField']);
        static::assertEquals([new NotBlank()], $ruleConstraints['selectedField']);
        static::assertEquals([new NotBlank()], $ruleConstraints['selectedFieldSet']);
        static::assertEquals(
            [
                new NotBlank(),
                new Choice(
                    choices: [
                        Rule::OPERATOR_BETWEEN,
                        Rule::OPERATOR_NEQ,
                        Rule::OPERATOR_GTE,
                        Rule::OPERATOR_LTE,
                        Rule::OPERATOR_EQ,
                        Rule::OPERATOR_GT,
                        Rule::OPERATOR_LT,
                    ]
                ),
            ],
            $ruleConstraints['operator']
        );
    }

    public function testGetConstraintsWithRenderedField(): void
    {
        $ruleConstraints = CustomFieldRule::getConstraints(['type' => 'string']);

        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints);
    }

    /**
     * @param array<string, string> $renderedField
     * @param list<Constraint> $expected
     */
    #[DataProvider('constraintsProvider')]
    public function testGetConstraintsRenderedFieldValue(array $renderedField, array $expected): void
    {
        $constraints = CustomFieldRule::getConstraints($renderedField);

        static::assertEquals($expected, $constraints['renderedFieldValue']);
    }

    /**
     * @return iterable<string, array{renderedField: array<string, string>, expected: list<Constraint>}>
     */
    public static function constraintsProvider(): iterable
    {
        $dateConstraints = [
            new NotBlank(),
            new AtLeastOneOf([
                new Type('string'),
                new Collection(
                    fields: [
                        'from' => [new NotBlank(), new Type('string')],
                        'to' => [new NotBlank(), new Type('string')],
                    ],
                    allowExtraFields: false,
                    allowMissingFields: false
                ),
            ]),
        ];

        yield 'default' => [
            'renderedField' => ['type' => CustomFieldTypes::TEXT],
            'expected' => [new NotBlank()],
        ];

        yield 'no field type' => [
            'renderedField' => [],
            'expected' => [new NotBlank()],
        ];

        yield 'bool field type' => [
            'renderedField' => ['type' => CustomFieldTypes::BOOL],
            'expected' => [],
        ];

        yield 'date field type' => [
            'renderedField' => ['type' => CustomFieldTypes::DATE],
            'expected' => $dateConstraints,
        ];

        yield 'datetime field type' => [
            'renderedField' => ['type' => CustomFieldTypes::DATETIME],
            'expected' => $dateConstraints,
        ];
    }

    /**
     * @param array<string, string|float|bool|list<string>|null> $customFields
     * @param string|float|bool|list<string>|array{from?: string, to?: string}|null $renderedFieldValue
     * @param array{componentName: string}|array{} $config
     */
    #[DataProvider('customFieldRuleMatchDataProvider')]
    public function testCustomFieldRuleMatchesValues(
        array $customFields,
        array|bool|string|float|null $renderedFieldValue,
        string $type,
        string $operator,
        bool $isMatching,
        array $config = []
    ): void {
        $renderedField = [
            'type' => $type,
            'name' => self::CUSTOM_FIELD_NAME,
            'config' => $config,
        ];

        static::assertSame(
            $isMatching,
            CustomFieldRule::match($renderedField, $renderedFieldValue, $operator, $customFields)
        );
    }

    /**
     * @return \Generator<string, array{array<string, string|float|bool|list<string>|null>, string|float|bool|list<string>|array{from?: string, to?: string}|null, string, string, bool, 5?: array{componentName: string}}>
     */
    public static function customFieldRuleMatchDataProvider(): \Generator
    {
        // All boolean custom field types should behave the same
        yield from self::boolTypeDataProvider(CustomFieldTypes::BOOL);
        yield from self::boolTypeDataProvider(CustomFieldTypes::SWITCH);
        yield from self::boolTypeDataProvider(CustomFieldTypes::CHECKBOX);

        yield from self::textTypeDataProvider();
        yield from self::stringTypeDataProvider();
        yield from self::floatTypeDataProvider();
        yield from self::selectTypeDataProvider();
        yield from self::datetimeTypeDataProvider();
        yield from self::dateTypeDataProvider();
    }

    public function testPriceFieldUsesGrossWithoutContext(): void
    {
        $priceCollection = new PriceCollection([
            new Price(Defaults::CURRENCY, 84.03, 100.0, false),
        ]);

        $renderedField = [
            'type' => CustomFieldTypes::PRICE,
            'name' => self::CUSTOM_FIELD_NAME,
            'config' => [],
        ];

        $value = CustomFieldRule::getValue([self::CUSTOM_FIELD_NAME => $priceCollection], $renderedField);

        static::assertSame(100.0, $value);
    }

    public function testPriceFieldUsesGrossWithGrossTaxState(): void
    {
        $priceCollection = new PriceCollection([
            new Price(Defaults::CURRENCY, 84.03, 100.0, false),
        ]);

        $renderedField = [
            'type' => CustomFieldTypes::PRICE,
            'name' => self::CUSTOM_FIELD_NAME,
            'config' => [],
        ];

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_GROSS);

        $value = CustomFieldRule::getValue([self::CUSTOM_FIELD_NAME => $priceCollection], $renderedField, $context);

        static::assertSame(100.0, $value);
    }

    public function testPriceFieldUsesNetWithNetTaxState(): void
    {
        $priceCollection = new PriceCollection([
            new Price(Defaults::CURRENCY, 84.03, 100.0, false),
        ]);

        $renderedField = [
            'type' => CustomFieldTypes::PRICE,
            'name' => self::CUSTOM_FIELD_NAME,
            'config' => [],
        ];

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_NET);

        $value = CustomFieldRule::getValue([self::CUSTOM_FIELD_NAME => $priceCollection], $renderedField, $context);

        static::assertSame(84.03, $value);
    }

    public function testPriceFieldReturnsNullWhenCurrencyNotInCollection(): void
    {
        $priceCollection = new PriceCollection([
            new Price(Uuid::randomHex(), 50.0, 60.0, false),
        ]);

        $renderedField = [
            'type' => CustomFieldTypes::PRICE,
            'name' => self::CUSTOM_FIELD_NAME,
            'config' => [],
        ];

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);

        $value = CustomFieldRule::getValue([self::CUSTOM_FIELD_NAME => $priceCollection], $renderedField, $context);

        static::assertNull($value);
    }

    /**
     * @param array<string, string>|float|bool|int|string|null $expectedFieldValue
     * @param array<string, string>|float|bool|int|string|null $renderedFieldValue
     * @param array<string, string> $renderedField
     */
    #[DataProvider('getExpectedValueDataProvider')]
    public function testGetExpectedValue(
        array|float|bool|int|string|null $expectedFieldValue,
        array|float|bool|int|string|null $renderedFieldValue,
        array $renderedField,
    ): void {
        static::assertEquals(
            $expectedFieldValue,
            CustomFieldRule::getExpectedValue($renderedFieldValue, $renderedField)
        );
    }

    /**
     * @return iterable<string, array{expectedFieldValue: array<string, string>|float|bool|int|string|null, renderedFieldValue: array<string, string>|float|bool|int|string|null, renderedField: array<string, string>}>
     */
    public static function getExpectedValueDataProvider(): iterable
    {
        yield 'text type passes string through' => [
            'expectedFieldValue' => 'my_text',
            'renderedFieldValue' => 'my_text',
            'renderedField' => ['type' => CustomFieldTypes::TEXT],
        ];

        yield 'float type passes number through' => [
            'expectedFieldValue' => 12.5,
            'renderedFieldValue' => 12.5,
            'renderedField' => ['type' => CustomFieldTypes::FLOAT],
        ];

        yield 'bool type parses string "true" to true' => [
            'expectedFieldValue' => true,
            'renderedFieldValue' => 'true',
            'renderedField' => ['type' => CustomFieldTypes::BOOL],
        ];

        yield 'bool type parses string "false" to false' => [
            'expectedFieldValue' => false,
            'renderedFieldValue' => 'false',
            'renderedField' => ['type' => CustomFieldTypes::BOOL],
        ];

        yield 'bool type parses string "1" to true' => [
            'expectedFieldValue' => true,
            'renderedFieldValue' => '1',
            'renderedField' => ['type' => CustomFieldTypes::BOOL],
        ];

        yield 'bool type parses arbitrary string to false' => [
            'expectedFieldValue' => false,
            'renderedFieldValue' => 'something',
            'renderedField' => ['type' => CustomFieldTypes::BOOL],
        ];

        yield 'switch type returns false on null' => [
            'expectedFieldValue' => false,
            'renderedFieldValue' => null,
            'renderedField' => ['type' => CustomFieldTypes::SWITCH],
        ];

        yield 'checkbox type returns true on bool true' => [
            'expectedFieldValue' => true,
            'renderedFieldValue' => true,
            'renderedField' => ['type' => CustomFieldTypes::CHECKBOX],
        ];

        yield 'date type returns null when value is null' => [
            'expectedFieldValue' => null,
            'renderedFieldValue' => null,
            'renderedField' => ['type' => CustomFieldTypes::DATE],
        ];

        yield 'date type formats scalar string to DATE_ATOM' => [
            'expectedFieldValue' => '2025-02-25T00:00:00+00:00',
            'renderedFieldValue' => '2025-02-25T00:00:00+00:00',
            'renderedField' => ['type' => CustomFieldTypes::DATE],
        ];

        yield 'datetime type formats Z-suffixed string to DATE_ATOM' => [
            'expectedFieldValue' => '2025-02-25T11:00:00+00:00',
            'renderedFieldValue' => '2025-02-25T11:00:00.000Z',
            'renderedField' => ['type' => CustomFieldTypes::DATETIME],
        ];

        yield 'datetime type formats from/to array to DATE_ATOM' => [
            'expectedFieldValue' => [
                'from' => '2025-02-25T10:00:00+00:00',
                'to' => '2025-02-25T12:00:00+00:00',
            ],
            'renderedFieldValue' => [
                'from' => '2025-02-25T10:00:00.000Z',
                'to' => '2025-02-25T12:00:00.000Z',
            ],
            'renderedField' => ['type' => CustomFieldTypes::DATETIME],
        ];
    }

    /**
     * @return \Generator<string, array{array<string, bool>, bool|string|null, string, string, bool}>
     */
    private static function boolTypeDataProvider(string $boolCustomFieldType): \Generator
    {
        yield $boolCustomFieldType . ': does not match missing value equals bool true' => [
            [],
            true,
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            false,
        ];

        yield $boolCustomFieldType . ': does match missing value equals bool false' => [
            [],
            false,
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does not match bool false equals bool true' => [
            [self::CUSTOM_FIELD_NAME => false],
            true,
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            false,
        ];

        yield $boolCustomFieldType . ': does match bool false equals bool false' => [
            [self::CUSTOM_FIELD_NAME => false],
            false,
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does not match bool true equals bool false' => [
            [self::CUSTOM_FIELD_NAME => true],
            false,
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            false,
        ];

        yield $boolCustomFieldType . ': does match bool true equals bool true' => [
            [self::CUSTOM_FIELD_NAME => true],
            true,
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool true equals "yes"' => [
            [self::CUSTOM_FIELD_NAME => true],
            'yes',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool true equals "yes "' => [
            [self::CUSTOM_FIELD_NAME => true],
            'yes ',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool true equals "True"' => [
            [self::CUSTOM_FIELD_NAME => true],
            'True',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool true equals "true"' => [
            [self::CUSTOM_FIELD_NAME => true],
            'true',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool true equals "1"' => [
            [self::CUSTOM_FIELD_NAME => true],
            '1',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does not match bool false equals "yes"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'yes',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            false,
        ];

        yield $boolCustomFieldType . ': does not match bool false with "yes "' => [
            [self::CUSTOM_FIELD_NAME => false],
            'yes ',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            false,
        ];

        yield $boolCustomFieldType . ': does not match bool false with "True"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'True',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            false,
        ];

        yield $boolCustomFieldType . ': does not match bool false with "true"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'true',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            false,
        ];

        yield $boolCustomFieldType . ': does not match bool false with "1"' => [
            [self::CUSTOM_FIELD_NAME => false],
            '1',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            false,
        ];

        yield $boolCustomFieldType . ': does match bool false equals "no"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'no',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool false equals "no "' => [
            [self::CUSTOM_FIELD_NAME => false],
            'no ',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool false equals "False"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'False',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool false equals "false"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'false',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool false equals "0"' => [
            [self::CUSTOM_FIELD_NAME => false],
            '0',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool false equals "some string"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'some string',
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];

        yield $boolCustomFieldType . ': does match bool false equals null' => [
            [self::CUSTOM_FIELD_NAME => false],
            null,
            $boolCustomFieldType,
            Rule::OPERATOR_EQ,
            true,
        ];
    }

    /**
     * @return \Generator<string, array{array<string, null>, string, string, string, bool}>
     */
    private static function textTypeDataProvider(): \Generator
    {
        yield 'does match null not equals "testValue"' => [
            [self::CUSTOM_FIELD_NAME => null],
            'testValue',
            'text',
            Rule::OPERATOR_NEQ,
            true,
        ];

        yield 'does match missing value equals "testValue"' => [
            [],
            'testValue',
            'text',
            Rule::OPERATOR_EQ,
            false,
        ];
    }

    /**
     * @return \Generator<string, array{array<string, string>, string, string, string, bool}>
     */
    private static function stringTypeDataProvider(): \Generator
    {
        yield 'does match same strings on equals' => [
            [self::CUSTOM_FIELD_NAME => 'my_test_value'],
            'my_test_value',
            'string',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does not match different strings on equals' => [
            [self::CUSTOM_FIELD_NAME => 'my_test_value'],
            'my_invalid_value',
            'string',
            Rule::OPERATOR_EQ,
            false,
        ];
    }

    /**
     * @return \Generator<string, array{array<string, float>, float, string, string, bool}>
     */
    private static function floatTypeDataProvider(): \Generator
    {
        yield 'does match same float on equals' => [
            [self::CUSTOM_FIELD_NAME => 123.0],
            123.0,
            'float',
            Rule::OPERATOR_EQ,
            true,
        ];
    }

    /**
     * @return \Generator<string, array{array<string, list<string>>, list<string>|null, string, string, bool, 5?: array{componentName: string}}>
     */
    private static function selectTypeDataProvider(): \Generator
    {
        yield 'does not match selected options equals null' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            null,
            'select',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does match selected options include certain option in multi-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            ['option_1'],
            'select',
            Rule::OPERATOR_EQ,
            true,
            ['componentName' => 'sw-multi-select'],
        ];

        yield 'does match selected options partially include certain options in multi-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_2', 'option_3']],
            ['option_1', 'option_2'],
            'select',
            Rule::OPERATOR_EQ,
            true,
            ['componentName' => 'sw-multi-select'],
        ];

        yield 'does not match selected options include different option in multi-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            ['option_3'],
            'select',
            Rule::OPERATOR_EQ,
            false,
            ['componentName' => 'sw-multi-select'],
        ];

        yield 'does not match selected options include different options in multi-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            ['option_3', 'option_4'],
            'select',
            Rule::OPERATOR_EQ,
            false,
            ['componentName' => 'sw-multi-select'],
        ];

        yield 'does not match selected options do not include certain option in multi-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            ['option_1'],
            'select',
            Rule::OPERATOR_NEQ,
            false,
            ['componentName' => 'sw-multi-select'],
        ];

        yield 'does match selected options do not include different option in multi-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            ['option_3'],
            'select',
            Rule::OPERATOR_NEQ,
            true,
            ['componentName' => 'sw-multi-select'],
        ];

        yield 'does not match selected options include null in multi-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            null,
            'select',
            Rule::OPERATOR_EQ,
            false,
            ['componentName' => 'sw-multi-select'],
        ];

        yield 'does match selected options partially include certain options in entity-multi-id-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_2', 'option_3']],
            ['option_1', 'option_2'],
            'select',
            Rule::OPERATOR_EQ,
            true,
            ['componentName' => 'sw-entity-multi-id-select'],
        ];

        yield 'does not match selected options include different options in entity-multi-id-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            ['option_3', 'option_4'],
            'select',
            Rule::OPERATOR_EQ,
            false,
            ['componentName' => 'sw-entity-multi-id-select'],
        ];

        yield 'does not match selected options do not include certain option in entity-multi-id-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            ['option_1'],
            'select',
            Rule::OPERATOR_NEQ,
            false,
            ['componentName' => 'sw-entity-multi-id-select'],
        ];

        yield 'does match selected options do not include different option in entity-multi-id-select component' => [
            [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
            ['option_3'],
            'select',
            Rule::OPERATOR_NEQ,
            true,
            ['componentName' => 'sw-entity-multi-id-select'],
        ];
    }

    /**
     * @return \Generator<string, array{array<string, string>, string|array{from?: string, to?: string}|null, string, string, bool}>
     */
    private static function datetimeTypeDataProvider(): \Generator
    {
        yield 'does not match missing value equals datetime' => [
            [],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does match missing value not equals datetime' => [
            [],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_NEQ,
            true,
        ];

        yield 'does match different datetimes on not equals' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:20:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_NEQ,
            true,
        ];

        yield 'does match same datetimes on greater then/equals' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_GTE,
            true,
        ];

        yield 'does match greater datetime bigger then/equals smaller datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T12:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_GTE,
            true,
        ];

        yield 'does not match smaller datetime greater then/equals bigger datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T10:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_GTE,
            false,
        ];

        yield 'does match same datetimes on less then/equals' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_LTE,
            true,
        ];

        yield 'does not match bigger datetime less then/equals smaller datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T12:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_LTE,
            false,
        ];

        yield 'does match smaller datetime less then/equals bigger datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T10:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_LTE,
            true,
        ];

        yield 'does match same datetimes on equals' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does not match different datetimes on equals' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T10:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does match bigger datetime greater then smaller datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T12:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_GT,
            true,
        ];

        yield 'does not match smaller datetime greater then bigger datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T10:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_GT,
            false,
        ];

        yield 'does not match bigger datetime less then smaller datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T12:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_LT,
            false,
        ];

        yield 'does match smaller datetime less then bigger datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T10:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_LT,
            true,
        ];

        yield 'does match datetime inside between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:00:00+00:00'],
            ['from' => '2025-02-25T10:00:00.000Z', 'to' => '2025-02-25T12:00:00.000Z'],
            'datetime',
            Rule::OPERATOR_BETWEEN,
            true,
        ];

        yield 'does match datetime on lower bound of between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T10:00:00+00:00'],
            ['from' => '2025-02-25T10:00:00.000Z', 'to' => '2025-02-25T12:00:00.000Z'],
            'datetime',
            Rule::OPERATOR_BETWEEN,
            true,
        ];

        yield 'does match datetime on upper bound of between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T12:00:00+00:00'],
            ['from' => '2025-02-25T10:00:00.000Z', 'to' => '2025-02-25T12:00:00.000Z'],
            'datetime',
            Rule::OPERATOR_BETWEEN,
            true,
        ];

        yield 'does not match datetime before between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T09:00:00+00:00'],
            ['from' => '2025-02-25T10:00:00.000Z', 'to' => '2025-02-25T12:00:00.000Z'],
            'datetime',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match datetime after between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T13:00:00+00:00'],
            ['from' => '2025-02-25T10:00:00.000Z', 'to' => '2025-02-25T12:00:00.000Z'],
            'datetime',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with missing from on datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:00:00+00:00'],
            ['to' => '2025-02-25T12:00:00.000Z'],
            'datetime',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with missing to on datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:00:00+00:00'],
            ['from' => '2025-02-25T10:00:00.000Z'],
            'datetime',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with scalar string value on datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:00:00+00:00'],
            '2025-02-25T11:00:00.000Z',
            'datetime',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with missing actual datetime value' => [
            [],
            ['from' => '2025-02-25T10:00:00.000Z', 'to' => '2025-02-25T12:00:00.000Z'],
            'datetime',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with null rendered value on datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:00:00+00:00'],
            null,
            'datetime',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match equals with null rendered value on datetime' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T11:00:00+00:00'],
            null,
            'datetime',
            Rule::OPERATOR_EQ,
            false,
        ];
    }

    /**
     * @return \Generator<string, array{array<string, string>, string|array{from?: string, to?: string}|null, string, string, bool}>
     */
    private static function dateTypeDataProvider(): \Generator
    {
        yield 'does not match missing value equals date' => [
            [],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does match missing value not equals date' => [
            [],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_NEQ,
            true,
        ];

        yield 'does match different dates on not equals' => [
            [self::CUSTOM_FIELD_NAME => '2024-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_NEQ,
            true,
        ];

        yield 'does match same dates on greater then/equals' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_GTE,
            true,
        ];

        yield 'does match greater date bigger then/equals smaller date' => [
            [self::CUSTOM_FIELD_NAME => '2026-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_GTE,
            true,
        ];

        yield 'does not match smaller date greater then/equals bigger date' => [
            [self::CUSTOM_FIELD_NAME => '2024-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_GTE,
            false,
        ];

        yield 'does match same dates on less then/equals' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_LTE,
            true,
        ];

        yield 'does not match bigger date less then/equals smaller date' => [
            [self::CUSTOM_FIELD_NAME => '2026-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_LTE,
            false,
        ];

        yield 'does match smaller date less then/equals bigger date' => [
            [self::CUSTOM_FIELD_NAME => '2024-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_LTE,
            true,
        ];

        yield 'does match same dates on equals' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does not match different dates on equals' => [
            [self::CUSTOM_FIELD_NAME => '2024-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does match bigger date greater then smaller date' => [
            [self::CUSTOM_FIELD_NAME => '2026-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_GT,
            true,
        ];

        yield 'does not match smaller date greater then bigger date' => [
            [self::CUSTOM_FIELD_NAME => '2024-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_GT,
            false,
        ];

        yield 'does not match bigger date less then smaller date' => [
            [self::CUSTOM_FIELD_NAME => '2026-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_LT,
            false,
        ];

        yield 'does match smaller date less then bigger date' => [
            [self::CUSTOM_FIELD_NAME => '2024-02-25T00:00:00+00:00'],
            '2025-02-25T00:00:00',
            'date',
            Rule::OPERATOR_LT,
            true,
        ];

        yield 'does match date inside between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-15T00:00:00+00:00'],
            ['from' => '2025-02-01T00:00:00', 'to' => '2025-02-28T00:00:00'],
            'date',
            Rule::OPERATOR_BETWEEN,
            true,
        ];

        yield 'does match date on lower bound of between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-01T00:00:00+00:00'],
            ['from' => '2025-02-01T00:00:00', 'to' => '2025-02-28T00:00:00'],
            'date',
            Rule::OPERATOR_BETWEEN,
            true,
        ];

        yield 'does match date on upper bound of between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-28T00:00:00+00:00'],
            ['from' => '2025-02-01T00:00:00', 'to' => '2025-02-28T00:00:00'],
            'date',
            Rule::OPERATOR_BETWEEN,
            true,
        ];

        yield 'does not match date before between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-01-31T00:00:00+00:00'],
            ['from' => '2025-02-01T00:00:00', 'to' => '2025-02-28T00:00:00'],
            'date',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match date after between range' => [
            [self::CUSTOM_FIELD_NAME => '2025-03-01T00:00:00+00:00'],
            ['from' => '2025-02-01T00:00:00', 'to' => '2025-02-28T00:00:00'],
            'date',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with missing from on date' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-15T00:00:00+00:00'],
            ['to' => '2025-02-28T00:00:00'],
            'date',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with missing to on date' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-15T00:00:00+00:00'],
            ['from' => '2025-02-01T00:00:00'],
            'date',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with scalar string value on date' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-15T00:00:00+00:00'],
            '2025-02-15T00:00:00',
            'date',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with missing actual date value' => [
            [],
            ['from' => '2025-02-01T00:00:00', 'to' => '2025-02-28T00:00:00'],
            'date',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match between with null rendered value on date' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-15T00:00:00+00:00'],
            null,
            'date',
            Rule::OPERATOR_BETWEEN,
            false,
        ];

        yield 'does not match equals with null rendered value on date' => [
            [self::CUSTOM_FIELD_NAME => '2025-02-15T00:00:00+00:00'],
            null,
            'date',
            Rule::OPERATOR_EQ,
            false,
        ];
    }
}
