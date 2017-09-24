<?php
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

namespace Shopware\Cart\Test\Common;

use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CartPersisterInterface;

class ArrayCartPersister implements CartPersisterInterface
{
    private $carts = [];

    private $signatures = [];

    public function loadBySignature(string $signature): string
    {
        return $this->signatures[$signature];
    }

    public function load(string $token): CartContainer
    {
        if (array_key_exists($token, $this->carts)) {
            return $this->carts[$token];
        }

        return null;
    }

    public function save(CartContainer $cartContainer): void
    {
        $this->carts[$cartContainer->getToken()] = $cartContainer;
    }
}
