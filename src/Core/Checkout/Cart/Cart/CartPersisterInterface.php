<?php
declare(strict_types=1);
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

namespace Shopware\Core\Checkout\Cart\Cart;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;

interface CartPersisterInterface
{
    /**
     * @param string $token
     * @param string $name
     *
     * @throws CartTokenNotFoundException
     *
     * @return Cart
     */
    public function load(string $token, string $name, CheckoutContext $context): Cart;

    /**
     * @param string $token
     * @param string $name
     *
     * @throws CartTokenNotFoundException
     *
     * @return CalculatedCart
     */
    public function loadCalculated(string $token, string $name, CheckoutContext $context): CalculatedCart;

    public function save(CalculatedCart $cart, CheckoutContext $context): void;

    public function delete(string $token, ?string $name = null, CheckoutContext $context): void;
}
