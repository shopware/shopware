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
use Shopware\Checkout\Cart\Test\Common\Generator;
use Shopware\Checkout\CartBridge\Product\ProductProcessor;
use Shopware\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Context\Rule\CalculatedLineItem\LineItemOfTypeRule;
use Shopware\Context\Struct\StorefrontContext;

class LineItemOfTypeRuleTest extends TestCase
{
    public function testRuleWithProductTypeMatch(): void
    {
        $rule = new LineItemOfTypeRule(ProductProcessor::TYPE_PRODUCT);

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 1);
        $context = $this->createMock(StorefrontContext::class);

        $this->assertTrue(
            $rule->match(new CalculatedLineItemMatchContext($calculatedLineItem, $context))->matches()
        );
    }

    public function testRuleWithProductTypeNotMatch(): void
    {
        $rule = new LineItemOfTypeRule('voucher');

        $calculatedLineItem = Generator::createCalculatedProduct('A', 100, 1);
        $context = $this->createMock(StorefrontContext::class);

        $this->assertFalse(
            $rule->match(new CalculatedLineItemMatchContext($calculatedLineItem, $context))->matches()
        );
    }
}
