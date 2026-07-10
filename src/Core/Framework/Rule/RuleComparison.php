<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\FloatComparator;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-final
 */
#[Package('fundamentals@after-sales')]
class RuleComparison
{
    public static function numeric(?float $itemValue, ?float $ruleValue, string $operator): bool
    {
        if ($itemValue === null) {
            return self::isNegativeOperator($operator);
        }

        if ($operator === Rule::OPERATOR_EMPTY) {
            return false;
        }

        if ($ruleValue === null) {
            return self::isNegativeOperator($operator);
        }

        return match ($operator) {
            Rule::OPERATOR_GTE => FloatComparator::greaterThanOrEquals($itemValue, $ruleValue),
            Rule::OPERATOR_LTE => FloatComparator::lessThanOrEquals($itemValue, $ruleValue),
            Rule::OPERATOR_GT => FloatComparator::greaterThan($itemValue, $ruleValue),
            Rule::OPERATOR_LT => FloatComparator::lessThan($itemValue, $ruleValue),
            Rule::OPERATOR_EQ => FloatComparator::equals($itemValue, $ruleValue),
            Rule::OPERATOR_NEQ => FloatComparator::notEquals($itemValue, $ruleValue),
            default => throw RuleException::unsupportedOperator($operator, self::class),
        };
    }

    public static function string(?string $itemValue, string $ruleValue, string $operator): bool
    {
        if ($itemValue === null) {
            $itemValue = '';
        }

        return match ($operator) {
            Rule::OPERATOR_EQ => strcasecmp($ruleValue, $itemValue) === 0,
            Rule::OPERATOR_NEQ => strcasecmp($ruleValue, $itemValue) !== 0,
            Rule::OPERATOR_EMPTY => trim($itemValue) === '',
            default => throw RuleException::unsupportedOperator($operator, self::class),
        };
    }

    /**
     * @param list<string> $ruleValue
     */
    public static function stringArray(?string $itemValue, array $ruleValue, string $operator): bool
    {
        if ($itemValue === null) {
            return false;
        }

        return match ($operator) {
            Rule::OPERATOR_EQ => \in_array(mb_strtolower($itemValue), $ruleValue, true),
            Rule::OPERATOR_NEQ => !\in_array(mb_strtolower($itemValue), $ruleValue, true),
            default => throw RuleException::unsupportedOperator($operator, self::class),
        };
    }

    /**
     * @param array<string|null>|null $itemValue
     * @param list<string|null>|null $ruleValue
     */
    public static function uuids(?array $itemValue, ?array $ruleValue, string $operator): bool
    {
        if (!$itemValue) {
            $itemValue = [];
        }

        if (!$ruleValue) {
            $ruleValue = [];
        }

        $diff = array_intersect($itemValue, $ruleValue);

        return match ($operator) {
            Rule::OPERATOR_EQ => $diff !== [],
            Rule::OPERATOR_NEQ => $diff === [],
            Rule::OPERATOR_EMPTY => $itemValue === [],
            default => throw RuleException::unsupportedOperator($operator, self::class),
        };
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-extension - `$ruleValue` becomes type `\DateTime|string|array`, will replace `dateValue()`
     */
    public static function date(\DateTime $itemValue, \DateTime $ruleValue, string $operator): bool
    {
        return self::compareDate(Defaults::STORAGE_DATE_FORMAT, $itemValue, $ruleValue, $operator);
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-extension - `$ruleValue` becomes type `\DateTime|string|array`, will replace `datetimeValue()`
     */
    public static function datetime(\DateTime $itemValue, \DateTime $ruleValue, string $operator): bool
    {
        return self::compareDate(Defaults::STORAGE_DATE_TIME_FORMAT, $itemValue, $ruleValue, $operator);
    }

    /**
     * @internal - will be removed in v6.8.0, when the original `date()` has extended type
     *
     * @param \DateTime|string|array{from?: \DateTime|string, to?: \DateTime|string} $ruleValue
     */
    public static function dateValue(\DateTime $itemValue, \DateTime|string|array $ruleValue, string $operator): bool
    {
        return self::compareDateValue(Defaults::STORAGE_DATE_FORMAT, $itemValue, $ruleValue, $operator);
    }

    /**
     * @internal - will be removed in v6.8.0, when the original `date()` has extended type
     *
     * @param \DateTime|string|array{from?: \DateTime|string, to?: \DateTime|string} $ruleValue
     */
    public static function datetimeValue(\DateTime $itemValue, \DateTime|string|array $ruleValue, string $operator): bool
    {
        return self::compareDateValue(Defaults::STORAGE_DATE_TIME_FORMAT, $itemValue, $ruleValue, $operator);
    }

    public static function isNegativeOperator(string $operator): bool
    {
        return \in_array($operator, [
            Rule::OPERATOR_EMPTY,
            Rule::OPERATOR_NEQ,
        ], true);
    }

    private static function compareDate(string $format, \DateTime $itemValue, \DateTime $ruleValue, string $operator): bool
    {
        return match ($operator) {
            Rule::OPERATOR_EQ => $itemValue->format($format) === $ruleValue->format($format),
            Rule::OPERATOR_NEQ => $itemValue->format($format) !== $ruleValue->format($format),
            Rule::OPERATOR_GT => $itemValue > $ruleValue,
            Rule::OPERATOR_LT => $itemValue < $ruleValue,
            Rule::OPERATOR_GTE => $itemValue >= $ruleValue,
            Rule::OPERATOR_LTE => $itemValue <= $ruleValue,
            default => throw RuleException::unsupportedOperator($operator, self::class),
        };
    }

    /**
     * @param \DateTime|string|array{from?: \DateTime|string, to?: \DateTime|string} $ruleValue
     */
    private static function compareDateValue(string $format, \DateTime $itemValue, \DateTime|string|array $ruleValue, string $operator): bool
    {
        try {
            if ($operator === Rule::OPERATOR_BETWEEN) {
                if (!\is_array($ruleValue) || !isset($ruleValue['from'], $ruleValue['to'])) {
                    return false;
                }

                return self::isDateBetween(
                    $format,
                    $itemValue,
                    self::toDateTime($ruleValue['from']),
                    self::toDateTime($ruleValue['to']),
                );
            }

            if (\is_array($ruleValue)) {
                return false;
            }

            $parsed = self::toDateTime($ruleValue);
        } catch (\Exception) {
            return false;
        }

        return self::compareDate($format, $itemValue, $parsed, $operator);
    }

    private static function isDateBetween(string $format, \DateTime $itemValue, \DateTime $from, \DateTime $to): bool
    {
        $itemDate = $itemValue->format($format);

        return $itemDate >= $from->format($format) && $itemDate <= $to->format($format);
    }

    private static function toDateTime(\DateTime|string $value): \DateTime
    {
        return $value instanceof \DateTime ? $value : new \DateTime($value);
    }
}
