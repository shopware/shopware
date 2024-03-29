<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\CustomFieldRule;

/**
 * @internal
 */
#[Package('services-settings')]
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
     * @param array<string, mixed> $customFields
     * @param array<string>|bool|string|int|null $renderedFieldValue
     * @param array<string, string> $config
     */
    #[DataProvider('customFieldRuleData')]
    public function testMatch(
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

        static::assertEquals(CustomFieldRule::match($renderedField, $renderedFieldValue, $operator, $customFields), $isMatching);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function customFieldRuleData(): array
    {
        return [
            'custom field null / rendered field value true/ bool type/ EQ operator / not matching' => [
                [],
                true,
                'bool',
                '=',
                false,
            ],
            'custom field null / rendered field value false/ bool type/ EQ operator / matching' => [
                [],
                false,
                'bool',
                '=',
                true,
            ],
            'custom field false value / rendered field value true/ bool type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                true,
                'bool',
                '=',
                false,
            ],
            'custom field false value / rendered field value false/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                false,
                'bool',
                '=',
                true,
            ],
            'custom field true value / rendered field value false/ bool type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => true],
                false,
                'bool',
                '=',
                false,
            ],
            'custom field true value / rendered field value true/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => true],
                true,
                'bool',
                '=',
                true,
            ],
            'custom field true value / rendered field value string "yes"/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => true],
                'yes',
                'bool',
                '=',
                true,
            ],
            'custom field true value / rendered field value string "yes "/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => true],
                'yes ',
                'bool',
                '=',
                true,
            ],
            'custom field true value / rendered field value string "True"/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => true],
                'True',
                'bool',
                '=',
                true,
            ],
            'custom field true value / rendered field value string "true"/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => true],
                'true',
                'bool',
                '=',
                true,
            ],
            'custom field true value / rendered field value string "1"/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => true],
                '1',
                'bool',
                '=',
                true,
            ],
            'custom field false value / rendered field value string "yes"/ bool type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                'yes',
                'bool',
                '=',
                false,
            ],
            'custom field false value / rendered field value string "yes "/ bool type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                'yes ',
                'bool',
                '=',
                false,
            ],
            'custom field false value / rendered field value string "True"/ bool type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                'True',
                'bool',
                '=',
                false,
            ],
            'custom field false value / rendered field value string "true"/ bool type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                'true',
                'bool',
                '=',
                false,
            ],
            'custom field false value / rendered field value string "1"/ bool type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                '1',
                'bool',
                '=',
                false,
            ],
            'custom field false value / rendered field value string "no"/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                'no',
                'bool',
                '=',
                true,
            ],
            'custom field false value / rendered field value string "no "/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                'no ',
                'bool',
                '=',
                true,
            ],
            'custom field false value / rendered field value string "False"/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                'False',
                'bool',
                '=',
                true,
            ],
            'custom field false value / rendered field value string "false"/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                'false',
                'bool',
                '=',
                true,
            ],
            'custom field false value / rendered field value string "0"/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                '0',
                'bool',
                '=',
                true,
            ],
            'custom field false value / rendered field value string "some string"/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                'some string',
                'bool',
                '=',
                true,
            ],
            'custom field false value / rendered field null/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                null,
                'bool',
                '=',
                true,
            ],
            'custom field null value / rendered field string value "testValue"/ text type/ NEQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => null],
                'testValue',
                'text',
                '!=',
                true,
            ],
            'custom field empty value / rendered field string value "testValue"/ text type/ EQ operator / not matching' => [
                [],
                'testValue',
                'text',
                '=',
                false,
            ],
            'custom field string value / rendered field string value "my_test_value"/ string type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => 'my_test_value'],
                'my_test_value',
                'string',
                '=',
                true,
            ],
            'custom field string value / rendered field string value "my_test_value"/ string type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => 'my_invalid_value'],
                'my_test_value',
                'string',
                '=',
                false,
            ],
            'custom field false value / rendered field string value false/ bool type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                false,
                'bool',
                '=',
                true,
            ],
            'custom field false value / rendered field string value true/ bool type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => false],
                true,
                'bool',
                '=',
                false,
            ],
            'custom field "my_test_value" value / rendered field string value "my_test_value"/ string type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => 'my_test_value'],
                'my_test_value',
                'string',
                '=',
                true,
            ],
            'custom field "my_test_value" value / rendered field string value "my_invalid_value"/ string type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => 'my_test_value'],
                'my_invalid_value',
                'string',
                '=',
                false,
            ],
            'custom field "123.0" value / rendered field float value "123"/ string type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => 123.0],
                123,
                'float',
                '=',
                true,
            ],
            'custom field null / rendered field multi select value ["option_1", "option_2"]/ select type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                null,
                'select',
                '=',
                false,
            ],
            'custom field "[option_1, option_2]" value / rendered field multi select value "[option_1]"/ select type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                ['option_1'],
                'select',
                '=',
                true,
                ['componentName' => 'sw-multi-select'],
            ],
            'custom field "[option_2, option_3]" value / rendered field multi select value "[option_1, option_2]"/ select type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_2', 'option_3']],
                ['option_1', 'option_2'],
                'select',
                '=',
                true,
                ['componentName' => 'sw-multi-select'],
            ],
            'custom field "[option_1, option_2]" value / rendered field multi select value "[option_3]"/ select type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                ['option_3'],
                'select',
                '=',
                false,
                ['componentName' => 'sw-multi-select'],
            ],
            'custom field "[option_1, option_2]" value / rendered field multi select value "[option_3, option_4]"/ select type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                ['option_3', 'option_4'],
                'select',
                '=',
                false,
                ['componentName' => 'sw-multi-select'],
            ],
            'custom field "[option_1, option_2]" value / rendered field multi select value "[option_1]"/ select type/ NEQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                ['option_1'],
                'select',
                '!=',
                false,
                ['componentName' => 'sw-multi-select'],
            ],
            'custom field "[option_1, option_2]" value / rendered field multi select value "[option_3]"/ select type/ NEQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                ['option_3'],
                'select',
                '!=',
                true,
                ['componentName' => 'sw-multi-select'],
            ],
            'custom field ["option_1", "option_2"] / rendered field multi select value null/ select type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                null,
                'select',
                '=',
                false,
                ['componentName' => 'sw-multi-select'],
            ],
            'custom field "[option_2, option_3]" value / rendered field entity multi id select value "[option_1, option_2]"/ select type/ EQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_2', 'option_3']],
                ['option_1', 'option_2'],
                'select',
                '=',
                true,
                ['componentName' => 'sw-entity-multi-id-select'],
            ],
            'custom field "[option_1, option_2]" value / rendered field entity multi id select value "[option_3, option_4]"/ select type/ EQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                ['option_3', 'option_4'],
                'select',
                '=',
                false,
                ['componentName' => 'sw-entity-multi-id-select'],
            ],
            'custom field "[option_1, option_2]" value / rendered field entity multi id select value "[option_1]"/ select type/ NEQ operator / not matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                ['option_1'],
                'select',
                '!=',
                false,
                ['componentName' => 'sw-entity-multi-id-select'],
            ],
            'custom field "[option_1, option_2]" value / rendered field entity multi id select value "[option_3]"/ select type/ NEQ operator / matching' => [
                [self::CUSTOM_FIELD_NAME => ['option_1', 'option_2']],
                ['option_3'],
                'select',
                '!=',
                true,
                ['componentName' => 'sw-entity-multi-id-select'],
            ],
        ];
    }
}
