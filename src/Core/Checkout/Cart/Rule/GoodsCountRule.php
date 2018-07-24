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

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

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
    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CartRuleScope) {
            return new Match(
                false,
                ['Invalid Match Context. CartRuleScope expected']
            );
        }

        $goods = $scope->getCart()->getLineItems()->filterGoods();

        switch ($this->operator) {
            case self::OPERATOR_GTE:

                return new Match(
                    $goods->count() >= $this->count,
                    ['Goods count too much']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $goods->count() <= $this->count,
                    ['Goods count too less']
                );

            case self::OPERATOR_EQ:

                return new Match(
                    $goods->count() === $this->count,
                    ['Goods count not equal']
                );

            case self::OPERATOR_NEQ:

                return new Match(
                    $goods->count() !== $this->count,
                    ['Goods count equal']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
