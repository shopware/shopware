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

namespace Shopware\Application\Context\Rule\CalculatedCart;

use Shopware\Application\Context\Exception\UnsupportedOperatorException;
use Shopware\Application\Context\Rule\MatchContext\CartRuleMatchContext;
use Shopware\Application\Context\Rule\MatchContext\RuleMatchContext;
use Shopware\Application\Context\Rule\Match;
use Shopware\Application\Context\Rule\Rule;

class OrderAmountRule extends Rule
{
    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(float $amount, string $operator)
    {
        $this->amount = $amount;
        $this->operator = $operator;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(
        RuleMatchContext $matchContext
    ): Match {
        if (!$matchContext instanceof CartRuleMatchContext) {
            return new Match(
                false,
                ['Invalid Match Context. CartRuleMatchContext expected']
            );
        }
        $cartAmount = $matchContext->getCalculatedCart()->getPrice()->getTotalPrice();

        switch ($this->operator) {
            case self::OPERATOR_GTE:

                return new Match(
                    $cartAmount >= $this->amount,
                    ['Total price too low']
                );

            case self::OPERATOR_LTE:

                return new Match(
                    $cartAmount <= $this->amount,
                    ['Total price too high']
                );

            case self::OPERATOR_EQ:

                return new Match(
                    $cartAmount == $this->amount,
                    ['Total price is not equal']
                );

            case self::OPERATOR_NEQ:

                return new Match(
                    $cartAmount != $this->amount,
                    ['Total price is equal']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
