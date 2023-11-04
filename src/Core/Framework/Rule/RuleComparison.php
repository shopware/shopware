<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Util\FloatComparator;

#[Package('business-ops')]
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
            default => throw new UnsupportedOperatorException($operator, self::class),
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
            Rule::OPERATOR_EMPTY => empty(trim($itemValue)),
            default => throw new UnsupportedOperatorException($operator, self::class),
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
            default => throw new UnsupportedOperatorException($operator, self::class),
        };
    }

    /**
     * @param list<string|null>|null $itemValue
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
            Rule::OPERATOR_EQ => !empty($diff),
            Rule::OPERATOR_NEQ => empty($diff),
            Rule::OPERATOR_EMPTY => empty($itemValue),
            default => throw new UnsupportedOperatorException($operator, self::class),
        };
    }

    public static function datetime(\DateTime $itemValue, \DateTime $ruleValue, string $operator): bool
    {
        return match ($operator) {
            Rule::OPERATOR_EQ => $itemValue->format('Y-m-d H:i:s') === $ruleValue->format('Y-m-d H:i:s'),
            Rule::OPERATOR_NEQ => $itemValue->format('Y-m-d H:i:s') !== $ruleValue->format('Y-m-d H:i:s'),
            Rule::OPERATOR_GT => $itemValue > $ruleValue,
            Rule::OPERATOR_LT => $itemValue < $ruleValue,
            Rule::OPERATOR_GTE => $itemValue >= $ruleValue,
            Rule::OPERATOR_LTE => $itemValue <= $ruleValue,
            default => throw new UnsupportedOperatorException($operator, self::class),
        };
    }

    public static function isNegativeOperator(string $operator): bool
    {
        return \in_array($operator, [
            Rule::OPERATOR_EMPTY,
            Rule::OPERATOR_NEQ,
        ], true);
    }
}
