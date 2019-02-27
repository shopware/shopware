<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;

class PriceRoundingTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testWithValidNumbers(float $price, float $expected, int $precision): void
    {
        $rounding = new PriceRounding();
        static::assertEquals($expected, $rounding->round($price, $precision));
    }

    public function getCases(): array
    {
        return [
            [0, 0, 0],
            [0, 0, 1],
            [0, 0, 2],

            [1, 1, 0],
            [1, 1, 1],
            [1, 1, 2],
            [1, 1, 3],

            [1.1, 1, 0],
            [1.1, 1.1, 1],
            [1.1, 1.1, 2],
            [1.1, 1.1, 3],

            [1.4444, 1, 0],
            [1.4444, 1.4, 1],
            [1.4444, 1.44, 2],
            [1.4444, 1.444, 3],

            [0.55555, 1, 0],
            [0.55555, 0.6, 1],
            [0.55555, 0.56, 2],
            [0.55555, 0.556, 3],

            [-1.4444, -1, 0],
            [-1.4444, -1.4, 1],
            [-1.4444, -1.44, 2],
            [-1.4444, -1.444, 3],

            [-1.55555, -2, 0],
            [-1.55555, -1.6, 1],
            [-1.55555, -1.56, 2],
            [-1.55555, -1.556, 3],
        ];
    }
}
