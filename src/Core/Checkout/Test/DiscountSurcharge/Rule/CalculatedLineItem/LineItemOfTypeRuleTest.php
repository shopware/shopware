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
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Content\Product\Cart\ProductProcessor;

class LineItemOfTypeRuleTest extends TestCase
{
    public function testRuleWithProductTypeMatch(): void
    {
        $rule = new LineItemOfTypeRule(ProductProcessor::TYPE_PRODUCT);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 1);
        $context = $this->createMock(CheckoutContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithProductTypeNotMatch(): void
    {
        $rule = new LineItemOfTypeRule('voucher');

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 1);
        $context = $this->createMock(CheckoutContext::class);

        $this->assertFalse(
            $rule->match(new CalculatedLineItemScope($calculatedLineItem, $context))->matches()
        );
    }
}
