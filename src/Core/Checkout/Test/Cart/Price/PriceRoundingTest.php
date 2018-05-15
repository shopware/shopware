<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Checkout\Cart\Price\PriceRounding;

class PriceRoundingTest extends TestCase
{
    /**
     * @dataProvider getCases
     *
     * @param $price
     * @param $expected
     * @param $precision
     */
    public function testWithValidNumbers($price, $expected, $precision): void
    {
        $rounding = new PriceRounding($precision);
        static::assertEquals($expected, $rounding->round($price));
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
