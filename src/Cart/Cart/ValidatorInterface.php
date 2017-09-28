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

namespace Shopware\Cart\Cart;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

interface ValidatorInterface
{
    /**
     * Validates the provided calculated cart.
     * If a validator decides, that the cart isn't valid, their are two opportunities:
     *
     * 1. Add an error to the calculated cart by using $cart->getErrors()->add()
     * This error is displayed in the store front and forces and customer action to mark the cart as valid
     *
     * 2. Return `false` and remove/change items from the `CartContainer` which stored inside the `CalculatedCart`
     * By returning `false` the cart will be recalculated
     *
     * @param CalculatedCart                       $cart
     * @param \Shopware\Context\Struct\ShopContext $context
     * @param StructCollection                     $dataCollection
     *
     * @return bool
     */
    public function validate(CalculatedCart $cart, ShopContext $context, StructCollection $dataCollection): bool;
}
