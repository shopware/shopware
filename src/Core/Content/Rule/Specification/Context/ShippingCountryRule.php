<?php declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Content\Rule\Specification\Context;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Content\Rule\Specification\Match;
use Shopware\Core\Content\Rule\Specification\Rule;
use Shopware\Core\Content\Rule\Specification\Scope\RuleScope;

class ShippingCountryRule extends Rule
{
    /**
     * @var int[]
     */
    protected $countryIds;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(array $countryIds, string $operator)
    {
        $this->countryIds = $countryIds;
        $this->operator = $operator;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(
        RuleScope $scope
    ): Match {
        $context = $scope->getContext();
        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match(
                    in_array($context->getShippingLocation()->getCountry()->getId(), $this->countryIds, true),
                    ['Shipping country id not matched']
                );

            case self::OPERATOR_NEQ:
                return new Match(
                    !in_array($context->getShippingLocation()->getCountry()->getId(), $this->countryIds, true),
                    ['Shipping country id matched']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
