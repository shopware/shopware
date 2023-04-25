<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 * The helper to provider static methods for custom fields rule.
 */
#[Package('business-ops')]
class CustomFieldRule
{
    /**
     * @param array<string, string> $renderedField
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
     * @param array<string, string> $renderedField
     * @param array<string, mixed> $customFields
     */
    public static function match(array $renderedField, string|int|bool|null|float $renderedFieldValue, string $operator, array $customFields): bool
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
            return self::floatMatch($operator, (float) $actual, (float) $expected);
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

    private static function floatMatch(string $operator, float $actual, float $expected): bool
    {
        return match ($operator) {
            Rule::OPERATOR_NEQ => FloatComparator::notEquals($actual, $expected),
            Rule::OPERATOR_GTE => FloatComparator::greaterThanOrEquals($actual, $expected),
            Rule::OPERATOR_LTE => FloatComparator::lessThanOrEquals($actual, $expected),
            Rule::OPERATOR_EQ => FloatComparator::equals($actual, $expected),
            Rule::OPERATOR_GT => FloatComparator::greaterThan($actual, $expected),
            Rule::OPERATOR_LT => FloatComparator::lessThan($actual, $expected),
            default => throw new UnsupportedOperatorException($operator, self::class),
        };
    }

    /**
     * @param array<string, string> $renderedField
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
     * @param array<string, mixed> $customFields
     * @param array<string, string> $renderedField
     */
    private static function getValue(array $customFields, array $renderedField): float|bool|int|string|null
    {
        if (!empty($customFields) && \array_key_exists($renderedField['name'], $customFields)) {
            return $customFields[$renderedField['name']];
        }

        if (self::isSwitchOrBoolField($renderedField)) {
            return false;
        }

        return null;
    }

    /**
     * @param array<string, string> $renderedField
     */
    private static function getExpectedValue(float|bool|int|string|null $renderedFieldValue, array $renderedField): float|bool|int|string|null
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
     * @param array<string, string> $renderedField
     */
    private static function isSwitchOrBoolField(array $renderedField): bool
    {
        return \in_array($renderedField['type'], [CustomFieldTypes::BOOL, CustomFieldTypes::SWITCH], true);
    }

    /**
     * @param array<string, string> $renderedField
     */
    private static function isFloat(array $renderedField): bool
    {
        return $renderedField['type'] === CustomFieldTypes::FLOAT;
    }
}
