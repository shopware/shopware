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

namespace Shopware\Cart\Rule;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\Struct;
use Shopware\Framework\Struct\StructCollection;

abstract class Rule extends Struct
{
    const OPERATOR_GTE = '=>';

    const OPERATOR_LTE = '<=';

    const OPERATOR_EQ = '=';

    const OPERATOR_NEQ = '!=';

    /**
     * Validate the current rule and returns a reason object which contains defines if the rule match and if not why not
     *
     * @param \Shopware\Cart\Cart\Struct\CalculatedCart          $calculatedCart
     * @param ShopContext                                 $context
     * @param \Shopware\Framework\Struct\StructCollection $collection
     *
     * @return Match
     */
    abstract public function match(
        CalculatedCart $calculatedCart,
        ShopContext $context,
        StructCollection $collection
    ): Match;
}
