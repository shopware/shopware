<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;

/**
 * @internal
 */
#[CoversClass(TaxCalculator::class)]
class TaxCalculatorTest extends TestCase
{
    #[DataProvider('netPricesToGross')]
    public function testCalculateGrossPriceOfNetPrice(float $expected, int $precision, TaxRule $taxRule, float $net): void
    {
        $calculator = new TaxCalculator();

        $rules = new TaxRuleCollection([$taxRule]);

        $rounding = new CashRounding();
        static::assertEquals(
            $expected,
            $rounding->cashRound(
                $calculator->calculateGross($net, $rules),
                new CashRoundingConfig($precision, 0.01, true)
            )
        );
    }

    /**
     * @return array<array{float, int, TaxRule, float}>
     */
    public static function netPricesToGross(): array
    {
        return [
            [0.01,         2, new TaxRule(7),      0.00934579439252336],
            [0.08,         2, new TaxRule(7),      0.0747663551401869],
            [5.00,         2, new TaxRule(7),      4.67289719626168],
            [299999.99,    2, new TaxRule(7),      280373.822429907],
            [13.76,        2, new TaxRule(7),      12.8598130841121],
            [12.15,        2, new TaxRule(7.1),    11.342342342423423],
            [369.76,       2, new TaxRule(7.01),   345.5343232312312312],
            [607.68,       2, new TaxRule(7.487),  565.3534534534534534],
            [0.001,        3, new TaxRule(7),      0.000934579439252336],
            [0.008,        3, new TaxRule(7),      0.00747663551401869],
            [5.00,         3, new TaxRule(7),      4.67289719626168],
            [299999.999,   3, new TaxRule(7),      280373.830841121],
            [13.767,       3, new TaxRule(7),      12.8663551401869],
            [19.824,       3, new TaxRule(7.6),    18.423424234234234],
            [34755.537,    3, new TaxRule(7.19),   32424.23423423424],
            [3686.41,      3, new TaxRule(7.343),  3434.23424234],
            [0.0001,       4, new TaxRule(7),      0.0000934579439252336],
            [0.0008,       4, new TaxRule(7),      0.000747663551401869],
            [5.00,         4, new TaxRule(7),      4.67289719626168],
            [299999.9999,  4, new TaxRule(7),      280373.831682243],
            [13.7676,      4, new TaxRule(7),      12.8669158878505],
            [26245.8487,   4, new TaxRule(7.9),    24324.234234234234],
            [9489.344,     4, new TaxRule(7.99),   8787.2432445333323],
            [84.6123,      4, new TaxRule(7.999),  78.345435345345],
            [0.01,         2, new TaxRule(19),     0.00840336134453782],
            [0.08,         2, new TaxRule(19),     0.0672268907563025],
            [5.00,         2, new TaxRule(19),     4.20168067226891],
            [299999.99,    2, new TaxRule(19),     252100.831932773],
            [13.76,        2, new TaxRule(19),     11.563025210084],
            [14.84,        2, new TaxRule(19.1),   12.4567656765756],
            [90.9,         2, new TaxRule(19.07),  76.343423424234],
            [4112.06,      2, new TaxRule(19.006), 3455.342342342424],
            [0.001,        3, new TaxRule(19),     0.000840336134453782],
            [0.008,        3, new TaxRule(19),     0.00672268907563025],
            [5.00,         3, new TaxRule(19),     4.20168067226891],
            [299999.999,   3, new TaxRule(19),     252100.839495798],
            [13.767,       3, new TaxRule(19),     11.5689075630252],
            [91.1,         2, new TaxRule(19.5),   76.234234234234],
            [27.74,        2, new TaxRule(19.34),  23.24324234],
            [147.94,       2, new TaxRule(19.936), 123.34534534534],
            [0.0001,       4, new TaxRule(19),     0.0000840336134453782],
            [0.0008,       4, new TaxRule(19),     0.000672268907563025],
            [5.00,         4, new TaxRule(19),     4.20168067226891],
            [299999.9999,  4, new TaxRule(19),     252100.840252101],
            [13.7676,      4, new TaxRule(19),     11.5694117647059],
            [4140798.5647, 4, new TaxRule(19.9),   3453543.4234234234],
            [1.708,        4, new TaxRule(19.99),  1.423423423423],
            [0.0041,       4, new TaxRule(19.999), 0.003424234],
        ];
    }
}
