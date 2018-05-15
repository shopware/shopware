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

namespace Shopware\Checkout\Cart\Test\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Test\Common\FalseRule;
use Shopware\Checkout\Cart\Test\Common\TrueRule;
use Shopware\Context\MatchContext\CartRuleMatchContext;
use Shopware\Context\MatchContext\StorefrontMatchContext;
use Shopware\Context\Rule\Container\AndRule;
use Shopware\Context\Rule\Match;
use Shopware\Context\Struct\StorefrontContext;

class AndRuleTest extends TestCase
{
    public function testTrue(): void
    {
        $rule = new AndRule([
            new TrueRule(),
            new TrueRule(),
        ]);

        $this->assertEquals(
            new Match(true),
            $rule->match(
                new StorefrontMatchContext(
                    $this->createMock(StorefrontContext::class)
                )
            )
        );
    }

    public function testFalse(): void
    {
        $rule = new AndRule([
            new TrueRule(),
            new FalseRule(),
        ]);

        $this->assertEquals(
            new Match(false, []),
            $rule->match(
                new CartRuleMatchContext(
                    $this->createMock(CalculatedCart::class),
                    $this->createMock(StorefrontContext::class)
                )
            )
        );
    }
}
