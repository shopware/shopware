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

namespace Shopware\CartBridge\Rule;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Rule\Exception\UnsupportedOperatorException;
use Shopware\Cart\Rule\Match;
use Shopware\Cart\Rule\Rule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

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

    public function match(
        CalculatedCart $calculatedCart,
        ShopContext $context,
        StructCollection $collection
    ): Match {
        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return new Match(
                    in_array($context->getShippingLocation()->getCountry()->getUuid(), $this->countryIds, true),
                    ['Shipping country id not matched']
                );

            case self::OPERATOR_NEQ:
                return new Match(
                    !in_array($context->getShippingLocation()->getCountry()->getUuid(), $this->countryIds, true),
                    ['Shipping country id matched']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
