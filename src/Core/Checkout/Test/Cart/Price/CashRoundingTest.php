<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;

class CashRoundingTest extends TestCase
{
    /**
     * @dataProvider roundingProvider
     */
    public function testCashRounding(float $price, float $expected, CashRoundingConfig $config): void
    {
        $service = new CashRounding();

        $actual = $service->cashRound($price, $config);
        static::assertEquals($expected, $actual);

        $actual = $service->cashRound($price * -1, $config);
        static::assertEquals($expected * -1, $actual);
    }

    public function roundingProvider()
    {
        $germany = new CashRoundingConfig(2, 0.01, true);
        $hongKong = new CashRoundingConfig(2, 0.10, true);
        $denmark = new CashRoundingConfig(2, 0.50, true);
        $italy = new CashRoundingConfig(2, 0.05, true);
        $sweden = new CashRoundingConfig(2, 1.0, true);

        return [
            '0.01 interval: 19.990 should be 19.99' => [19.990, 19.99, $germany],
            '0.01 interval: 19.991 should be 19.99' => [19.991, 19.99, $germany],
            '0.01 interval: 19.994 should be 19.99' => [19.994, 19.99, $germany],
            '0.01 interval: 19.995 should be 20.00' => [19.995, 20.00, $germany],
            '0.01 interval: 19.999 should be 20.00' => [19.999, 20.00, $germany],

            '0.05 interval: 19.50 should be 19.50' => [19.50, 19.50, $italy],
            '0.05 interval: 19.51 should be 19.50' => [19.51, 19.50, $italy],
            '0.05 interval: 19.52 should be 19.50' => [19.52, 19.50, $italy],
            '0.05 interval: 19.524 should be 19.50' => [19.524, 19.50, $italy],
            '0.05 interval: 19.525 should be 19.55' => [19.525, 19.55, $italy],
            '0.05 interval: 19.53 should be 19.55' => [19.53, 19.55, $italy],
            '0.05 interval: 19.54 should be 19.55' => [19.54, 19.55, $italy],
            '0.05 interval: 19.55 should be 19.55' => [19.55, 19.55, $italy],
            '0.05 interval: 19.56 should be 19.55' => [19.56, 19.55, $italy],
            '0.05 interval: 19.57 should be 19.55' => [19.57, 19.55, $italy],
            '0.05 interval: 19.574 should be 19.55' => [19.574, 19.55, $italy],
            '0.05 interval: 19.575 should be 19.60' => [19.575, 19.60, $italy],
            '0.05 interval: 19.58 should be 19.60' => [19.58, 19.60, $italy],
            '0.05 interval: 19.59 should be 19.60' => [19.59, 19.60, $italy],

            '0.10 interval: 19.40 should be 19.40' => [19.40, 19.40, $hongKong],
            '0.10 interval: 19.41 should be 19.40' => [19.41, 19.40, $hongKong],
            '0.10 interval: 19.44 should be 19.40' => [19.44, 19.40, $hongKong],
            '0.10 interval: 19.444 should be 19.40' => [19.444, 19.40, $hongKong],
            '0.10 interval: 19.445 should be 19.50' => [19.445, 19.50, $hongKong],
            '0.10 interval: 19.45 should be 19.50' => [19.45, 19.50, $hongKong],
            '0.10 interval: 19.49 should be 19.50' => [19.49, 19.50, $hongKong],

            '0.50 interval: 19.01 should be 19.00' => [19.01, 19.00, $denmark],
            '0.50 interval: 19.24 should be 19.00' => [19.24, 19.00, $denmark],
            '0.50 interval: 19.244 should be 19.00' => [19.244, 19.00, $denmark],
            '0.50 interval: 19.245 should be 19.50' => [19.245, 19.50, $denmark],
            '0.50 interval: 19.25 should be 19.50' => [19.25, 19.50, $denmark],
            '0.50 interval: 19.49 should be 19.50' => [19.49, 19.50, $denmark],
            '0.50 interval: 19.50 should be 19.50' => [19.50, 19.50, $denmark],
            '0.50 interval: 19.51 should be 19.50' => [19.51, 19.50, $denmark],
            '0.50 interval: 19.74 should be 19.50' => [19.74, 19.50, $denmark],
            '0.50 interval: 19.744 should be 19.50' => [19.744, 19.50, $denmark],
            '0.50 interval: 19.745 should be 20.00' => [19.745, 20.00, $denmark],
            '0.50 interval: 19.75 should be 20.00' => [19.75, 20.00, $denmark],
            '0.50 interval: 19.99 should be 20.00' => [19.99, 20.00, $denmark],

            '1.00 interval: 19.00 should be 19.00' => [19.00, 19.00, $sweden],
            '1.00 interval: 19.01 should be 19.00' => [19.01, 19.00, $sweden],
            '1.00 interval: 19.49 should be 19.00' => [19.49, 19.00, $sweden],
            '1.00 interval: 19.491 should be 19.00' => [19.491, 19.00, $sweden],
            '1.00 interval: 19.495 should be 20.00' => [19.495, 20.00, $sweden],
            '1.00 interval: 19.50 should be 20.00' => [19.50, 20.00, $sweden],
            '1.00 interval: 19.99 should be 20.00' => [19.99, 20.00, $sweden],
        ];
    }
}
