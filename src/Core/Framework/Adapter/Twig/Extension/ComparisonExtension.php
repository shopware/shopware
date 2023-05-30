<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Util\FloatComparator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('core')]
class ComparisonExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('compare', $this->compare(...)),
        ];
    }

    public function compare(string $operator, mixed $value, mixed $comparable): bool
    {
        switch ($operator) {
            case Rule::OPERATOR_EMPTY:
                return empty($value);

            case Rule::OPERATOR_EQ:
            case Rule::OPERATOR_NEQ:
                if (\is_array($comparable)) {
                    return $this->compareArray($operator, $value, $comparable);
                }
        }

        if (\is_array($comparable)) {
            $comparable = $comparable[0] ?? null;
        }

        if (is_numeric($value) && is_numeric($comparable)) {
            return $this->compareNumeric($operator, (float) $value, (float) $comparable);
        }

        return $this->compareMixed($operator, $value, $comparable);
    }

    private function compareArray(string $operator, mixed $value, array $comparable): bool
    {
        if (!\is_array($value)) {
            $value = [$value];
        }

        $matches = array_intersect($value, $comparable);

        return match ($operator) {
            Rule::OPERATOR_EQ => !empty($matches),
            Rule::OPERATOR_NEQ => empty($matches),
            default => throw new UnsupportedOperatorException($operator, self::class),
        };
    }

    private function compareMixed(string $operator, mixed $value, mixed $comparable): bool
    {
        return match ($operator) {
            Rule::OPERATOR_EQ => $value === $comparable,
            Rule::OPERATOR_NEQ => $value !== $comparable,
            Rule::OPERATOR_GTE => $value >= $comparable,
            Rule::OPERATOR_LTE => $value <= $comparable,
            Rule::OPERATOR_GT => $value > $comparable,
            Rule::OPERATOR_LT => $value < $comparable,
            default => throw new UnsupportedOperatorException($operator, self::class),
        };
    }

    private function compareNumeric(string $operator, float $value, float $comparable): bool
    {
        return match ($operator) {
            Rule::OPERATOR_EQ => FloatComparator::equals($value, $comparable),
            Rule::OPERATOR_NEQ => FloatComparator::notEquals($value, $comparable),
            Rule::OPERATOR_GTE => FloatComparator::greaterThanOrEquals($value, $comparable),
            Rule::OPERATOR_LTE => FloatComparator::lessThanOrEquals($value, $comparable),
            Rule::OPERATOR_GT => FloatComparator::greaterThan($value, $comparable),
            Rule::OPERATOR_LT => FloatComparator::lessThan($value, $comparable),
            default => throw new UnsupportedOperatorException($operator, self::class),
        };
    }
}
