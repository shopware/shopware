<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\MultiEntitySelectField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\MultiSelectField;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\ArrayComparator;
use Shopware\Core\Framework\Util\FloatComparator;
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
 * The helper to provider static methods for custom fields rule.
 */
#[Package('fundamentals@after-sales')]
class CustomFieldRule
{
    /**
     * @param array<string, string|array<string, string>> $renderedField
     *
     * @return array<string, list<Constraint>>
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
        ];
    }

    /**
     * @param array<string, string|array<string, string>> $renderedField
     * @param array<string, mixed> $customFields
     * @param array<string|int|bool|float>|array{from: string, to: string}|string|int|bool|float|null $renderedFieldValue
     */
    public static function match(array $renderedField, array|string|int|bool|float|null $renderedFieldValue, string $operator, array $customFields, ?SalesChannelContext $context = null): bool
    {
        $actual = self::getValue($customFields, $renderedField, $context);
        $expected = self::getExpectedValue($renderedFieldValue, $renderedField);

        if ($actual === null) {
            if ($operator === Rule::OPERATOR_NEQ) {
                return $actual !== $expected;
            }

            return false;
        }

        if (self::isFloat($renderedField) || self::isPrice($renderedField)) {
            return FloatComparator::compare((float) $actual, (float) $expected, $operator);
        }

        if (self::isArray($renderedField)) {
            return ArrayComparator::compare((array) $actual, (array) $expected, $operator);
        }

        if ($operator === Rule::OPERATOR_BETWEEN && self::isDatetimeOrDateField($renderedField)) {
            if (!\is_array($expected) || !isset($expected['from'], $expected['to'])) {
                return false;
            }

            return $actual >= $expected['from'] && $actual <= $expected['to'];
        }

        return match ($operator) {
            Rule::OPERATOR_NEQ => $actual !== $expected,
            Rule::OPERATOR_GTE => $actual >= $expected,
            Rule::OPERATOR_LTE => $actual <= $expected,
            Rule::OPERATOR_EQ => $actual === $expected,
            Rule::OPERATOR_GT => $actual > $expected,
            Rule::OPERATOR_LT => $actual < $expected,
            default => throw RuleException::unsupportedOperator($operator, self::class),
        };
    }

    /**
     * @param array<string, mixed> $customFields
     * @param array<string, string|array<string, string>> $renderedField
     *
     * @return array<string>|float|bool|int|string|null
     */
    public static function getValue(array $customFields, array $renderedField, ?SalesChannelContext $context = null): array|float|bool|int|string|null
    {
        if ($customFields !== [] && \is_string($renderedField['name']) && \array_key_exists($renderedField['name'], $customFields)) {
            $value = $customFields[$renderedField['name']];

            if (self::isPrice($renderedField) && $value instanceof PriceCollection) {
                $currencyId = $context?->getCurrencyId() ?? Defaults::CURRENCY;
                $price = $value->getCurrencyPrice($currencyId);

                if ($price === null) {
                    return null;
                }

                if ($context?->getTaxState() === CartPrice::TAX_STATE_NET) {
                    return $price->getNet();
                }

                return $price->getGross();
            }

            return $value;
        }

        if (self::isSwitchOrBoolField($renderedField)) {
            return false;
        }

        return null;
    }

    /**
     * @param array<string|int|bool|float>|float|bool|int|string|array{from: string, to: string}|null $renderedFieldValue
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

        if (self::isDatetimeOrDateField($renderedField)) {
            if ($renderedFieldValue === null) {
                return null;
            }

            if (\is_string($renderedFieldValue)) {
                return (new \DateTimeImmutable($renderedFieldValue))->format(\DATE_ATOM);
            }

            if (\is_array($renderedFieldValue) && isset($renderedFieldValue['from'], $renderedFieldValue['to'])) {
                return [
                    'from' => (new \DateTimeImmutable((string) $renderedFieldValue['from']))->format(\DATE_ATOM),
                    'to' => (new \DateTimeImmutable((string) $renderedFieldValue['to']))->format(\DATE_ATOM),
                ];
            }

            return null;
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
    public static function isPrice(array $renderedField): bool
    {
        return $renderedField['type'] === CustomFieldTypes::PRICE;
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
     * @return list<Constraint>
     */
    private static function getRenderedFieldValueConstraints(array $renderedField): array
    {
        if (!\array_key_exists('type', $renderedField)) {
            return [new NotBlank()];
        }

        if ($renderedField['type'] === CustomFieldTypes::BOOL) {
            return [];
        }

        // Date/datetime fields accept two payload shapes depending on the operator:
        // - scalar date string  (=, !=, >, <, >=, <=)
        // - array{from: string, to: string}  (BETWEEN)
        if (self::isDatetimeOrDateField($renderedField)) {
            return [
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
        }

        return [new NotBlank()];
    }

    /**
     * @param array<string, string|array<string, string>> $renderedField
     */
    private static function isSwitchOrBoolField(array $renderedField): bool
    {
        return \in_array($renderedField['type'], [CustomFieldTypes::BOOL, CustomFieldTypes::SWITCH, CustomFieldTypes::CHECKBOX], true);
    }

    /**
     * @param array<string, string|array<string, string>> $renderedField
     */
    private static function isDatetimeOrDateField(array $renderedField): bool
    {
        return \in_array($renderedField['type'], [CustomFieldTypes::DATETIME, CustomFieldTypes::DATE], true);
    }
}
