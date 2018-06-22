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

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\CalculatedLineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CalculatedLineItemScope;
use Shopware\Core\Checkout\Cart\Rule\ProductOfManufacturerRule;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerStruct;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Framework\Struct\Uuid;

class ProductOfManufacturerRuleTest extends TestCase
{
    public function testRuleMatch(): void
    {
        $product = $this->createProductWithManufacturer();
        $rule = new ProductOfManufacturerRule($product->getManufacturerId());

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2, $product);
        $context = $this->createMock(CheckoutContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleNotMatch(): void
    {
        $product = $this->createProductWithManufacturer();
        $rule = new ProductOfManufacturerRule(Uuid::uuid4()->getHex());

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 2, $product);
        $context = $this->createMock(CheckoutContext::class);

        $this->assertFalse(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    private function createProductWithManufacturer(): ProductStruct
    {
        $id = Uuid::optimize(Uuid::uuid4()->getHex());

        $manufacturer = new ProductManufacturerStruct();
        $manufacturer->setId($id);

        $product = new ProductStruct();
        $product->setManufacturer($manufacturer);
        $product->setManufacturerId($id);

        return $product;
    }
}
