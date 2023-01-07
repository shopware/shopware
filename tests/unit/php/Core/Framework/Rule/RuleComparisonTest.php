<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;

/**
 * @package business-ops
 *
 * @internal
 * @covers \Shopware\Core\Framework\Rule\RuleComparison
 */
class RuleComparisonTest extends TestCase
{
    /**
     * @dataProvider valuesForNumericEqualComparison
     */
    public function testNumericComparisonWithEqualOperator(?float $itemValue, ?float $ruleValue, bool $result): void
    {
        static::assertEquals($result, RuleComparison::numeric($itemValue, $ruleValue, Rule::OPERATOR_EQ));
    }

    public function valuesForNumericEqualComparison(): \Generator
    {
        yield 'Numeric: 5.1 = 5.1 should be true' => [5.1, 5.1, true];
        yield 'Numeric: 7 = 5 should be false' => [7, 5, false];
        yield 'Numeric: 0.0 = 0.0 should be true' => [0.0, 0.0, true];
        yield 'Numeric: -5.1 = 5.1 should be false' => [-5.1, 5.1, false];

        yield 'Numeric: 5.1 = null should be false' => [5.1, null, false];
        yield 'Numeric: null = 5.1 should be false' => [null, 5.1, false];
        yield 'Numeric: null = null should be false' => [null, null, false];
    }

    /**
     * @dataProvider valuesForNumericNotEqualComparison
     */
    public function testNumericComparisonWithNotEqualOperator(?float $itemValue, ?float $ruleValue, bool $result): void
    {
        static::assertEquals($result, RuleComparison::numeric($itemValue, $ruleValue, Rule::OPERATOR_NEQ));
    }

    public function valuesForNumericNotEqualComparison(): \Generator
    {
        yield 'Numeric: 7.1 != 5.1 should be true' => [7.1, 5.1, true];
        yield 'Numeric: 0.0 != 0.0 should be false' => [0.0, 0.0, false];
        yield 'Numeric: -5.1 != 5.1 should be true' => [-5.1, 5.1, true];

        yield 'Numeric: 5.1 != null should be true' => [5.1, null, true];
        yield 'Numeric: null != 5.1 should be true' => [null, 5.1, true];
        yield 'Numeric: null != null should be true' => [null, null, true];
    }

    /**
     * @dataProvider valuesForNumericGreaterThanComparison
     */
    public function testNumericComparisonWithGreaterThanOperator(?float $itemValue, ?float $ruleValue, bool $result): void
    {
        static::assertEquals($result, RuleComparison::numeric($itemValue, $ruleValue, Rule::OPERATOR_GT));
    }

    public function valuesForNumericGreaterThanComparison(): \Generator
    {
        yield 'Numeric: 7.1 > 5.1 should be true' => [7.1, 5.1, true];
        yield 'Numeric: 5.1 > 5.1 should be false' => [5.1, 5.1, false];
        yield 'Numeric: 5.1 > 7.1 should be false' => [5.1, 7.1, false];
        yield 'Numeric: 0.0 > 0.0 should be false' => [0.0, 0.0, false];
        yield 'Numeric: -7.1 > 5.1 should be false' => [-7.1, 5.1, false];
        yield 'Numeric: 5.1 > -7.1 should be true' => [5.1, -7.1, true];

        yield 'Numeric: 5.1 > null should be false' => [5.1, null, false];
        yield 'Numeric: null > 5.1 should be false' => [null, 5.1, false];
        yield 'Numeric: null > null should be false' => [null, null, false];
    }

    /**
     * @dataProvider valuesForLessThanOrEqualComparison
     */
    public function testNumericComparisonWithLessThanOrEqualOperator(?float $itemValue, ?float $ruleValue, bool $result): void
    {
        static::assertEquals($result, RuleComparison::numeric($itemValue, $ruleValue, Rule::OPERATOR_LTE));
    }

    public function valuesForLessThanOrEqualComparison(): \Generator
    {
        yield 'Numeric: 1.0 <= 1.0 should be true' => [1.0, 1.0, true];
        yield 'Numeric: 1.0 <= 2.0 should be true' => [1.0, 2.0, true];
        yield 'Numeric: -1.0 <= 2.0 should be true' => [-1.0, 2.0, true];
        yield 'Numeric: -1.0 <= -2.0 should be false' => [-1.0, -2.0, false];

        yield 'Numeric: null <= null should be false' => [null, null, false];
        yield 'Numeric: 1.0 <= null should be false' => [1.0, null, false];
        yield 'Numeric: null <= 1.0 should be false' => [null, 1.0, false];
        yield 'Numeric: -1.0 <= null should be false' => [-1.0, null, false];
        yield 'Numeric: null <= -1.0 should be false' => [null, -1.0, false];
    }

    /**
     * @dataProvider valuesForGreaterThanOrEqualComparison
     */
    public function testNumericComparisonWithGreaterThanOrEqualOperator(?float $itemValue, ?float $ruleValue, bool $result): void
    {
        static::assertEquals($result, RuleComparison::numeric($itemValue, $ruleValue, Rule::OPERATOR_GTE));
    }

    public function valuesForGreaterThanOrEqualComparison(): \Generator
    {
        yield 'Numeric: 1.0 >= 1.0 should be true' => [1.0, 1.0, true];
        yield 'Numeric: 1.0 >= 2.0 should be false' => [1.0, 2.0, false];
        yield 'Numeric: -1.0 >= 2.0 should be false' => [-1.0, 2.0, false];
        yield 'Numeric: -1.0 >= -2.0 should be true' => [-1.0, -2.0, true];

        yield 'Numeric: null >= null should be false' => [null, null, false];
        yield 'Numeric: 1.0 >= null should be false' => [1.0, null, false];
        yield 'Numeric: null >= 1.0 should be false' => [null, 1.0, false];
        yield 'Numeric: -1.0 >= null should be false' => [-1.0, null, false];
        yield 'Numeric: null >= -1.0 should be false' => [null, -1.0, false];
    }

    /**
     * @dataProvider valuesForLessThanComparison
     */
    public function testNumericComparisonWithLessThanOperator(?float $itemValue, ?float $ruleValue, bool $result): void
    {
        static::assertEquals($result, RuleComparison::numeric($itemValue, $ruleValue, Rule::OPERATOR_LT));
    }

    public function valuesForLessThanComparison(): \Generator
    {
        yield 'Numeric: 1.0 < 1.0 should be false' => [1.0, 1.0, false];
        yield 'Numeric: 1.0 < 2.0 should be true' => [1.0, 2.0, true];
        yield 'Numeric: -1.0 < 2.0 should be true' => [-1.0, 2.0, true];
        yield 'Numeric: -1.0 < -2.0 should be false' => [-1.0, -2.0, false];

        yield 'Numeric: null < null should be false' => [null, null, false];
        yield 'Numeric: 1.0 < null should be false' => [1.0, null, false];
        yield 'Numeric: null < 1.0 should be false' => [null, 1.0, false];
        yield 'Numeric: -1.0 < null should be false' => [-1.0, null, false];
        yield 'Numeric: null < -1.0 should be false' => [null, -1.0, false];
    }

    /**
     * @dataProvider valuesForNumericEmptyComparison
     */
    public function testNumericComparisonWithEmptyOperator(?float $itemValue, bool $result): void
    {
        static::assertEquals($result, RuleComparison::numeric($itemValue, null, Rule::OPERATOR_EMPTY));
    }

    public function valuesForNumericEmptyComparison(): \Generator
    {
        yield 'Numeric: 1.0 empty should be false' => [1.0, false];
        yield 'Numeric: -1.0 empty should be false' => [-1.0, false];
        yield 'Numeric: null empty should be true' => [null, true];
    }

    public function testNumericComparisonThrowsExceptionIfUnsupportedOperatorIsUsed(): void
    {
        $this->expectException(UnsupportedOperatorException::class);

        RuleComparison::numeric(1.0, 1.0, 'unsupported');
    }
}
