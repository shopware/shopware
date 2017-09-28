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

namespace Shopware\Cart\Test\Domain\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Rule\Container\AndRule;
use Shopware\Cart\Rule\Match;
use Shopware\Cart\Test\Common\FalseRule;
use Shopware\Cart\Test\Common\TrueRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

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
                $this->createMock(CalculatedCart::class),
                $this->createMock(ShopContext::class),
                new StructCollection()
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
                $this->createMock(CalculatedCart::class),
                $this->createMock(ShopContext::class),
                new StructCollection()
            )
        );
    }
}
