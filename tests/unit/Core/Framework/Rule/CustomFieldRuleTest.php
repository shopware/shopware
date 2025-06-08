<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\CustomFieldRule;
use Shopware\Core\Framework\Rule\Rule;

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

        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
        static::assertArrayHasKey('renderedField', $ruleConstraints, 'Rule Constraint renderedField is not defined');
        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints, 'Rule Constraint renderedFieldValue is not defined');
        static::assertArrayHasKey('selectedField', $ruleConstraints, 'Rule Constraint selectedField is not defined');
        static::assertArrayHasKey('selectedFieldSet', $ruleConstraints, 'Rule Constraint selectedFieldSet is not defined');
    }

    public function testGetConstraintsWithRenderedField(): void
    {
        $ruleConstraints = CustomFieldRule::getConstraints(['type' => 'string']);

        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints, 'Rule Constraint renderedFieldValue is not defined');
    }

    /**
     * @param array<string, array<string>|string|bool|float> $customFields
     * @param array<string>|bool|string|int|null $renderedFieldValue
     * @param array<string, string> $config
     */
    #[DataProvider('customFieldRuleMatchDataProvider')]
    public function testCustomFieldRuleMatchesValues(
        array $customFields,
        array|bool|string|int|null $renderedFieldValue,
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

        static::assertSame($isMatching, CustomFieldRule::match($renderedField, $renderedFieldValue, $operator, $customFields));
    }

    /**
     * @return iterable<string, array<array<string|int, array<string>|string|bool|float|null>|bool|string|int|null>>
     */
    public static function customFieldRuleMatchDataProvider(): iterable
    {
        yield from self::boolTypeDataProvider();
        yield from self::textTypeDataProvider();
        yield from self::stringTypeDataProvider();
        yield from self::floatTypeDataProvider();
        yield from self::selectTypeDataProvider();
        yield from self::datetimeTypeDataProvider();
        yield from self::dateTypeDataProvider();
    }

    /**
     * @return iterable<string, array<array<string, bool>|bool|string|null>>
     */
    private static function boolTypeDataProvider(): iterable
    {
        yield 'does not match missing value equals bool true' => [
            [],
            true,
            'bool',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does match missing value equals bool false' => [
            [],
            false,
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does not match bool false equals bool true' => [
            [self::CUSTOM_FIELD_NAME => false],
            true,
            'bool',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does match bool false equals bool false' => [
            [self::CUSTOM_FIELD_NAME => false],
            false,
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does not match bool true equals bool false' => [
            [self::CUSTOM_FIELD_NAME => true],
            false,
            'bool',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does match bool true equals bool true' => [
            [self::CUSTOM_FIELD_NAME => true],
            true,
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool true equals "yes"' => [
            [self::CUSTOM_FIELD_NAME => true],
            'yes',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool true equals "yes "' => [
            [self::CUSTOM_FIELD_NAME => true],
            'yes ',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool true equals "True"' => [
            [self::CUSTOM_FIELD_NAME => true],
            'True',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool true equals "true"' => [
            [self::CUSTOM_FIELD_NAME => true],
            'true',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool true equals "1"' => [
            [self::CUSTOM_FIELD_NAME => true],
            '1',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does not match bool false equals "yes"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'yes',
            'bool',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does not match bool false with "yes "' => [
            [self::CUSTOM_FIELD_NAME => false],
            'yes ',
            'bool',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does not match bool false with "True"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'True',
            'bool',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does not match bool false with "true"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'true',
            'bool',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does not match bool false with "1"' => [
            [self::CUSTOM_FIELD_NAME => false],
            '1',
            'bool',
            Rule::OPERATOR_EQ,
            false,
        ];

        yield 'does match bool false equals "no"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'no',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool false equals "no "' => [
            [self::CUSTOM_FIELD_NAME => false],
            'no ',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool false equals "False"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'False',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool false equals "false"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'false',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool false equals "0"' => [
            [self::CUSTOM_FIELD_NAME => false],
            '0',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool false equals "some string"' => [
            [self::CUSTOM_FIELD_NAME => false],
            'some string',
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];

        yield 'does match bool false equals null' => [
            [self::CUSTOM_FIELD_NAME => false],
            null,
            'bool',
            Rule::OPERATOR_EQ,
            true,
        ];
    }

    /**
     * @return iterable<string, array<array<string, null>|bool|string>>
     */
    private static function textTypeDataProvider(): iterable
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
     * @return iterable<string, array<array<string, string>|bool|string>>
     */
    private static function stringTypeDataProvider(): iterable
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
     * @return iterable<string, array<array<string, float>|bool|string|int>>
     */
    private static function floatTypeDataProvider(): iterable
    {
        yield 'does match same float on equals' => [
            [self::CUSTOM_FIELD_NAME => 123.0],
            123,
            'float',
            Rule::OPERATOR_EQ,
            true,
        ];
    }

    /**
     * @return iterable<string, array<array<string|int, array<string>|string>|bool|string|null>>
     */
    private static function selectTypeDataProvider(): iterable
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
     * @return iterable<string, array<array<string,string>|bool|string>>
     */
    private static function datetimeTypeDataProvider(): iterable
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
    }

    /**
     * @return iterable<string, array<array<string, string>|bool|string>>
     */
    private static function dateTypeDataProvider(): iterable
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
    }
}
