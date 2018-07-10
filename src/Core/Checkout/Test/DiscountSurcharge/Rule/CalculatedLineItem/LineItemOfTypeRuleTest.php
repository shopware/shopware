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
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Content\Product\Cart\ProductCollector;

class LineItemOfTypeRuleTest extends TestCase
{
    public function testRuleWithProductTypeMatch(): void
    {
        $rule = new LineItemOfTypeRule(ProductCollector::LINE_ITEM_TYPE);

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope(new LineItem('A', 'product'), $context))->matches()
        );
    }

    public function testRuleWithProductTypeNotMatch(): void
    {
        $rule = new LineItemOfTypeRule('voucher');

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope(new LineItem('A', 'product'), $context))->matches()
        );
    }
}
