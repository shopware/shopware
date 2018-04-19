<?php
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

namespace Shopware\tests\Unit\Components\BasketSignature;

use PHPUnit\Framework\TestCase;
use Shopware\Components\BasketSignature\BasketSignatureGenerator;

class BasketSignatureGeneratorTest extends TestCase
{
    public function testSignatureCanBeCreatedForEmptyBasket()
    {
        $signatureCreator = new BasketSignatureGenerator();
        $signature = $signatureCreator->generateSignature(
            [
                'sAmount' => 0,
                'sAmountTax' => 0,
                'content' => [],
            ],
            null
        );

        $this->assertNotEmpty($signature);
    }

    public function testSignatureConsidersItemTaxRate()
    {
        $signatureCreator = new BasketSignatureGenerator();

        $this->assertNotSame(
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(null, null, null, 19),
                    ],
                ],
                null
            ),
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(null, null, null, 22),
                    ],
                ],
                null
            )
        );
    }

    public function testSignatureConsidersItemQuantity()
    {
        $signatureCreator = new BasketSignatureGenerator();

        $this->assertNotSame(
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(null, 1, null, null),
                    ],
                ],
                null
            ),
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(null, 2, null, null),
                    ],
                ],
                null
            )
        );
    }

    public function testSignatureConsidersItemPrice()
    {
        $signatureCreator = new BasketSignatureGenerator();

        $this->assertNotSame(
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(100, null, null, null),
                    ],
                ],
                null
            ),
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(200, null, null, null),
                    ],
                ],
                null
            )
        );
    }

    public function testSignatureConsidersItemNumber()
    {
        $signatureCreator = new BasketSignatureGenerator();

        $this->assertNotSame(
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(null, null, 'A', null),
                    ],
                ],
                null
            ),
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(null, null, 'B', null),
                    ],
                ],
                null
            )
        );
    }

    public function testSignatureConsidersBasketAmount()
    {
        $signatureCreator = new BasketSignatureGenerator();

        $this->assertNotSame(
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 100,
                    'sAmountTax' => 0,
                    'content' => [],
                ],
                null
            ),
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 200,
                    'sAmountTax' => 0,
                    'content' => [],
                ],
                null
            )
        );
    }

    public function testSignatureConsidersBasketTaxAmount()
    {
        $signatureCreator = new BasketSignatureGenerator();

        $this->assertNotSame(
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 100,
                    'content' => [],
                ],
                null
            ),
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 200,
                    'content' => [],
                ],
                null
            )
        );
    }

    public function testSignatureConsidersMultipleItems()
    {
        $signatureCreator = new BasketSignatureGenerator();

        $this->assertNotSame(
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(10, null, null, null),
                        $this->createItemRow(10, null, null, null),
                        $this->createItemRow(10, null, null, null),
                        $this->createItemRow(10, null, null, null),
                    ],
                ],
                null
            ),
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(10, null, null, null),
                        $this->createItemRow(10, null, null, null),
                    ],
                ],
                null
            )
        );
    }

    public function testSignatureDoesNotConsidersItemOrder()
    {
        $signatureCreator = new BasketSignatureGenerator();

        $generator = $this->createPartialMock(BasketSignatureGenerator::class, []);
        $class = new \ReflectionClass($generator);
        $method = $class->getMethod('sortItems');
        $method->setAccessible(true);

        $this->assertSame(
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(10, 1, 'B', 19.00),
                        $this->createItemRow(20, 1, 'A', 19.00),
                    ],
                ],
                null
            ),
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [
                        $this->createItemRow(20, 1, 'A', 19.00),
                        $this->createItemRow(10, 1, 'B', 19.00),
                    ],
                ],
                null
            )
        );
    }

    public function testSortRandomItems()
    {
        $generator = $this->createPartialMock(BasketSignatureGenerator::class, []);
        $class = new \ReflectionClass($generator);
        $method = $class->getMethod('sortItems');
        $method->setAccessible(true);

        $items = [];
        // generate 100 random items
        foreach (range(0, 10) as $counter) {
            $items[] = $this->createItemRow(
                random_int(0, 10),
                random_int(0, 10),
                random_int(0, 10),
                random_int(0, 10)
            );
        }

        // sort items once for reference
        $expected = $method->invokeArgs($generator, [$items]);

        // shuffle the items and compare output to reference
        foreach (range(0, 10) as $counter) {
            shuffle($items);
            $this->assertEquals($expected, $method->invokeArgs($generator, [$items]));
        }
    }

    public function testSignatureConsidersCustomerId()
    {
        $signatureCreator = new BasketSignatureGenerator();

        $this->assertNotSame(
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [],
                ],
                1
            ),
            $signatureCreator->generateSignature(
                [
                    'sAmount' => 0,
                    'sAmountTax' => 0,
                    'content' => [],
                ],
                2
            )
        );
    }

    /**
     * @param float  $price
     * @param int    $quantity
     * @param string $number
     * @param float  $taxRate
     *
     * @return array
     */
    private function createItemRow(
        $price,
        $quantity,
        $number,
        $taxRate
    ) {
        return [
            'ordernumber' => $number,
            'price' => $price,
            'tax_rate' => $taxRate,
            'quantity' => $quantity,
        ];
    }
}
