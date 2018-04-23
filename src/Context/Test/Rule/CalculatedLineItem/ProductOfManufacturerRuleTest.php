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

namespace Shopware\Context\Test\Rule\CalculatedLineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\Product\Struct\ProductManufacturerBasicStruct;
use Shopware\Cart\Test\Common\Generator;
use Shopware\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Context\Rule\CalculatedLineItem\ProductOfManufacturerRule;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\Uuid;

class ProductOfManufacturerRuleTest extends TestCase
{
    public function testRuleMatch(): void
    {
        $product = $this->createProductWithManufacturer();
        $rule = new ProductOfManufacturerRule($product->getManufacturerId());

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2, $product);
        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemMatchContext($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleNotMatch(): void
    {
        $product = $this->createProductWithManufacturer();
        $rule = new ProductOfManufacturerRule(Uuid::uuid4()->getHex());

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2, $product);
        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match(new CalculatedLineItemMatchContext($calculatedLineItem, $context))->matches()
        );
    }

    private function createProductWithManufacturer(): ProductBasicStruct
    {
        $id = Uuid::optimize(Uuid::uuid4()->getHex());

        $manufacturer = new ProductManufacturerBasicStruct();
        $manufacturer->setId($id);

        $product = new ProductBasicStruct();
        $product->setManufacturer($manufacturer);
        $product->setManufacturerId($id);

        return $product;
    }
}
