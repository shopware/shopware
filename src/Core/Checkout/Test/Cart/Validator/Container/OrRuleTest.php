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

namespace Shopware\Core\Checkout\Test\Cart\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Match;

class OrRuleTest extends TestCase
{
    public function testTrue(): void
    {
        $rule = new OrRule([
            new TrueRule(),
            new FalseRule(),
        ]);

        static::assertEquals(
            new Match(true),
            $rule->match(
                new CheckoutRuleScope(
                    $this->createMock(CheckoutContext::class)
                )
            )
        );
    }

    public function testFalse(): void
    {
        $rule = new OrRule([
            new FalseRule(),
            new FalseRule(),
        ]);

        static::assertEquals(
            new Match(false),
            $rule->match(
                new CheckoutRuleScope(
                    $this->createMock(CheckoutContext::class)
                )
            )
        );
    }
}
