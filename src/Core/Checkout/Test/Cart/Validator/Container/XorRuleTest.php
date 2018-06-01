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

namespace Shopware\Checkout\Test\Cart\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Checkout\CustomerContext;
use Shopware\Checkout\Rule\Specification\Container\XorRule;
use Shopware\Checkout\Rule\Specification\Match;
use Shopware\Checkout\Rule\Specification\Scope\StorefrontScope;
use Shopware\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Checkout\Test\Cart\Common\TrueRule;

class XorRuleTest extends TestCase
{
    public function testSingleTrueRule(): void
    {
        $rule = new XorRule([
            new FalseRule(),
            new TrueRule(),
            new FalseRule(),
        ]);

        $this->assertEquals(
            new Match(true),
            $rule->match(
                new StorefrontScope(
                    $this->createMock(CustomerContext::class)
                )
            )
        );
    }

    public function testWithMultipleFalse(): void
    {
        $rule = new XorRule([
            new FalseRule(),
            new FalseRule(),
        ]);

        $this->assertEquals(
            new Match(false),
            $rule->match(
                new StorefrontScope(
                    $this->createMock(CustomerContext::class)
                )
            )
        );
    }

    public function testWithMultipleTrue(): void
    {
        $rule = new XorRule([
            new TrueRule(),
            new TrueRule(),
            new FalseRule(),
        ]);

        $this->assertEquals(
            new Match(false),
            $rule->match(
                new StorefrontScope(
                    $this->createMock(CustomerContext::class)
                )
            )
        );
    }
}
