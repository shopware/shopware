<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Util\FloatComparator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
class ComparisonExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('compare', [$this, 'compare']),
        ];
    }

    /**
     * @param mixed $value
     * @param mixed $comparable
     */
    public function compare(string $operator, $value, $comparable): bool
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

    /**
     * @param mixed $value
     */
    private function compareArray(string $operator, $value, array $comparable): bool
    {
        if (!\is_array($value)) {
            $value = [$value];
        }

        $matches = array_intersect($value, $comparable);

        switch ($operator) {
            case Rule::OPERATOR_EQ:
                return !empty($matches);

            case Rule::OPERATOR_NEQ:
                return empty($matches);

            default:
                throw new UnsupportedOperatorException($operator, self::class);
        }
    }

    /**
     * @param mixed $value
     * @param mixed $comparable
     */
    private function compareMixed(string $operator, $value, $comparable): bool
    {
        switch ($operator) {
            case Rule::OPERATOR_EQ:
                return $value === $comparable;

            case Rule::OPERATOR_NEQ:
                return $value !== $comparable;

            case Rule::OPERATOR_GTE:
                return $value >= $comparable;

            case Rule::OPERATOR_LTE:
                return $value <= $comparable;

            case Rule::OPERATOR_GT:
                return $value > $comparable;

            case Rule::OPERATOR_LT:
                return $value < $comparable;

            default:
                throw new UnsupportedOperatorException($operator, self::class);
        }
    }

    private function compareNumeric(string $operator, float $value, float $comparable): bool
    {
        switch ($operator) {
            case Rule::OPERATOR_EQ:
                return FloatComparator::equals($value, $comparable);

            case Rule::OPERATOR_NEQ:
                return FloatComparator::notEquals($value, $comparable);

            case Rule::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($value, $comparable);

            case Rule::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($value, $comparable);

            case Rule::OPERATOR_GT:
                return FloatComparator::greaterThan($value, $comparable);

            case Rule::OPERATOR_LT:
                return FloatComparator::lessThan($value, $comparable);

            default:
                throw new UnsupportedOperatorException($operator, self::class);
        }
    }
}
