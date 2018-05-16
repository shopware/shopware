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

namespace Shopware\Checkout\Rule\Specification\CalculatedCart;

use Shopware\Checkout\Cart\LineItem\GoodsInterface;
use Shopware\Checkout\Rule\Exception\UnsupportedOperatorException;
use Shopware\Checkout\Rule\Specification\Scope\CartRuleScope;
use Shopware\Checkout\Rule\Specification\Scope\RuleScope;
use Shopware\Checkout\Rule\Specification\Match;
use Shopware\Checkout\Rule\Specification\Rule;

class GoodsCountRule extends Rule
{
    /**
     * @var int
     */
    protected $count;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(int $count, string $operator = self::OPERATOR_EQ)
    {
        $this->count = $count;
        $this->operator = $operator;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(
        RuleScope $scope
    ): Match {
        if (!$scope instanceof CartRuleScope) {
            return new Match(
                false,
                ['Invalid Match Context. CartRuleScope expected']
            );
        }

        $goods = $scope->getCalculatedCart()->getCalculatedLineItems()->filterInstance(GoodsInterface::class);

        switch ($this->operator) {
            case self::OPERATOR_GTE:

                return new Match(
                    $goods->count() >= $this->count,
                    ['GoodsInterface count too much']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $goods->count() <= $this->count,
                    ['GoodsInterface count too less']
                );

            case self::OPERATOR_EQ:

                return new Match(
                    $goods->count() == $this->count,
                    ['GoodsInterface count not equal']
                );

            case self::OPERATOR_NEQ:
                return new Match(
                    $goods->count() != $this->count,
                    ['GoodsInterface count equal']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
