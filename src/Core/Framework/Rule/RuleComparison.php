<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Util\FloatComparator;

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

        switch ($operator) {
            case Rule::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($itemValue, $ruleValue);

            case Rule::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($itemValue, $ruleValue);

            case Rule::OPERATOR_GT:
                return FloatComparator::greaterThan($itemValue, $ruleValue);

            case Rule::OPERATOR_LT:
                return FloatComparator::lessThan($itemValue, $ruleValue);

            case Rule::OPERATOR_EQ:
                return FloatComparator::equals($itemValue, $ruleValue);

            case Rule::OPERATOR_NEQ:
                return FloatComparator::notEquals($itemValue, $ruleValue);

            default:
                throw new UnsupportedOperatorException($operator, self::class);
        }
    }

    public static function string(?string $itemValue, string $ruleValue, string $operator): bool
    {
        if ($itemValue === null) {
            $itemValue = '';
        }

        switch ($operator) {
            case Rule::OPERATOR_EQ:
                return strcasecmp($ruleValue, $itemValue) === 0;

            case Rule::OPERATOR_NEQ:
                return strcasecmp($ruleValue, $itemValue) !== 0;

            case Rule::OPERATOR_EMPTY:
                return empty(trim($itemValue));

            default:
                throw new UnsupportedOperatorException($operator, self::class);
        }
    }

    /**
     * @param list<string> $ruleValue
     */
    public static function stringArray(?string $itemValue, array $ruleValue, string $operator): bool
    {
        if ($itemValue === null) {
            return false;
        }

        switch ($operator) {
            case Rule::OPERATOR_EQ:
                return \in_array(mb_strtolower($itemValue), $ruleValue, true);

            case Rule::OPERATOR_NEQ:
                return !\in_array(mb_strtolower($itemValue), $ruleValue, true);

            default:
                throw new UnsupportedOperatorException($operator, self::class);
        }
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

        switch ($operator) {
            case Rule::OPERATOR_EQ:
                return !empty($diff);
            case Rule::OPERATOR_NEQ:
                return empty($diff);
            case Rule::OPERATOR_EMPTY:
                return empty($itemValue);
            default:
                throw new UnsupportedOperatorException($operator, self::class);
        }
    }

    public static function datetime(\DateTime $itemValue, \DateTime $ruleValue, string $operator): bool
    {
        switch ($operator) {
            case Rule::OPERATOR_EQ:
                // due to the cs fixer that always adds ===
                // its necessary to use the string when comparing, otherwise its never working
                return $itemValue->format('Y-m-d H:i:s') === $ruleValue->format('Y-m-d H:i:s');

            case Rule::OPERATOR_NEQ:
                // due to the cs fixer that always adds ===
                // its necessary to use the string when comparing, otherwise its never working
                return $itemValue->format('Y-m-d H:i:s') !== $ruleValue->format('Y-m-d H:i:s');

            case Rule::OPERATOR_GT:
                return $itemValue > $ruleValue;

            case Rule::OPERATOR_LT:
                return $itemValue < $ruleValue;

            case Rule::OPERATOR_GTE:
                return $itemValue >= $ruleValue;

            case Rule::OPERATOR_LTE:
                return $itemValue <= $ruleValue;

            default:
                throw new UnsupportedOperatorException($operator, self::class);
        }
    }

    public static function isNegativeOperator(string $operator): bool
    {
        return \in_array($operator, [
            Rule::OPERATOR_EMPTY,
            Rule::OPERATOR_NEQ,
        ], true);
    }
}
