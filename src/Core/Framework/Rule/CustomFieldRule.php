<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\MultiEntitySelectField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\MultiSelectField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Util\ArrayComparator;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 * The helper to provider static methods for custom fields rule.
 */
#[Package('services-settings')]
class CustomFieldRule
{
    /**
     * @param array<string, string|array<string, string>> $renderedField
     *
     * @return array<string, array<int, mixed>>
     */
    public static function getConstraints(array $renderedField): array
    {
        return [
            'renderedField' => [new NotBlank()],
            'selectedField' => [new NotBlank()],
            'selectedFieldSet' => [new NotBlank()],
            'renderedFieldValue' => self::getRenderedFieldValueConstraints($renderedField),
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        Rule::OPERATOR_NEQ,
                        Rule::OPERATOR_GTE,
                        Rule::OPERATOR_LTE,
                        Rule::OPERATOR_EQ,
                        Rule::OPERATOR_GT,
                        Rule::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    /**
     * @param array<string, string|array<string, string>> $renderedField
     * @param array<string, mixed> $customFields
     * @param array<string|int|bool|float>|string|int|bool|float|null $renderedFieldValue
     */
    public static function match(array $renderedField, array|string|int|bool|float|null $renderedFieldValue, string $operator, array $customFields): bool
    {
        $actual = self::getValue($customFields, $renderedField);
        $expected = self::getExpectedValue($renderedFieldValue, $renderedField);

        if ($actual === null) {
            if ($operator === Rule::OPERATOR_NEQ) {
                return $actual !== $expected;
            }

            return false;
        }

        if (self::isFloat($renderedField)) {
            return FloatComparator::compare((float) $actual, (float) $expected, $operator);
        }

        if (self::isArray($renderedField)) {
            return ArrayComparator::compare((array) $actual, (array) $expected, $operator);
        }

        return match ($operator) {
            Rule::OPERATOR_NEQ => $actual !== $expected,
            Rule::OPERATOR_GTE => $actual >= $expected,
            Rule::OPERATOR_LTE => $actual <= $expected,
            Rule::OPERATOR_EQ => $actual === $expected,
            Rule::OPERATOR_GT => $actual > $expected,
            Rule::OPERATOR_LT => $actual < $expected,
            default => throw new UnsupportedOperatorException($operator, self::class),
        };
    }

    /**
     * @deprecated tag:v6.7.0 - Method will be removed, use FloatComparator::compare instead
     */
    public static function floatMatch(string $operator, float $actual, float $expected): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'FloatComparator::compare')
        );

        return FloatComparator::compare($actual, $expected, $operator);
    }

    /**
     * @deprecated tag:v6.7.0 - Method will be removed, use ArrayComparator::compare instead
     *
     * @param array<string|int|bool|float> $actual
     * @param array<string|int|bool|float> $expected
     */
    public static function arrayMatch(string $operator, array $actual, array $expected): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'ArrayComparator::compare')
        );

        return ArrayComparator::compare($actual, $expected, $operator);
    }

    /**
     * @param array<string, mixed> $customFields
     * @param array<string, string|array<string, string>> $renderedField
     *
     * @return array<string>|float|bool|int|string|null
     */
    public static function getValue(array $customFields, array $renderedField): array|float|bool|int|string|null
    {
        if (!empty($customFields) && \is_string($renderedField['name']) && \array_key_exists($renderedField['name'], $customFields)) {
            return $customFields[$renderedField['name']];
        }

        if (self::isSwitchOrBoolField($renderedField)) {
            return false;
        }

        return null;
    }

    /**
     * @param array<string|int|bool|float>|float|bool|int|string|null $renderedFieldValue
     * @param array<string, string|array<string, string>> $renderedField
     *
     * @return array<string|int|bool|float>|float|bool|int|string|null
     */
    public static function getExpectedValue(array|float|bool|int|string|null $renderedFieldValue, array $renderedField): array|float|bool|int|string|null
    {
        if (self::isSwitchOrBoolField($renderedField) && \is_string($renderedFieldValue)) {
            return filter_var($renderedFieldValue, \FILTER_VALIDATE_BOOLEAN);
        }

        if (self::isSwitchOrBoolField($renderedField)) {
            return $renderedFieldValue ?? false; // those fields are initialized with null in the rule builder
        }

        return $renderedFieldValue;
    }

    /**
     * @param array<string, string|array<string, string>> $renderedField
     */
    public static function isFloat(array $renderedField): bool
    {
        return $renderedField['type'] === CustomFieldTypes::FLOAT;
    }

    /**
     * @param array<string, string|array<string, string>> $renderedField
     */
    public static function isArray(array $renderedField): bool
    {
        if ($renderedField['type'] !== CustomFieldTypes::SELECT) {
            return false;
        }

        if (!\is_array($renderedField['config'])) {
            return false;
        }

        if (!\array_key_exists('componentName', $renderedField['config'])) {
            return false;
        }

        if ($renderedField['config']['componentName'] === MultiSelectField::COMPONENT_NAME) {
            return true;
        }

        if ($renderedField['config']['componentName'] === MultiEntitySelectField::COMPONENT_NAME) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, string|array<string, string>> $renderedField
     *
     * @return Constraint[]
     */
    private static function getRenderedFieldValueConstraints(array $renderedField): array
    {
        $constraints = [];

        if (!\array_key_exists('type', $renderedField)) {
            return [new NotBlank()];
        }

        if ($renderedField['type'] !== CustomFieldTypes::BOOL) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    /**
     * @param array<string, string|array<string, string>> $renderedField
     */
    private static function isSwitchOrBoolField(array $renderedField): bool
    {
        return \in_array($renderedField['type'], [CustomFieldTypes::BOOL, CustomFieldTypes::SWITCH], true);
    }
}
