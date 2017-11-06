<?php
declare(strict_types=1);
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

namespace Shopware\Cart\Payment;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Cart\ValidatorInterface;
use Shopware\Cart\Error\PaymentBlockedError;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\IndexedCollection;

class PaymentValidatorProcessor implements ValidatorInterface
{
    public function validate(
        CalculatedCart $cart,
        ShopContext $context,
        IndexedCollection $dataCollection
    ): bool {
        if (!$context->getCustomer()) {
            return true;
        }

        $payment = $context->getPaymentMethod();

        if (!$payment->getRule()) {
            return true;
        }

        $valid = $payment->getRule()->match($cart, $context, $dataCollection);

        if ($valid->matches()) {
            return true;
        }

        $cart->getErrors()->add(
            new PaymentBlockedError($payment->getId(), $payment->getLabel())
        );

        return true;
    }
}
