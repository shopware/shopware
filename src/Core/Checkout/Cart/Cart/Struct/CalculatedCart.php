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

namespace Shopware\Core\Checkout\Cart\Cart\Struct;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Framework\Struct\Struct;

class CalculatedCart extends Struct
{
    /**
     * @var CartPrice
     */
    protected $price;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var CalculatedLineItemCollection
     */
    protected $calculatedLineItems;

    /**
     * @var DeliveryCollection
     */
    protected $deliveries;
    /**
     * @var TransactionCollection
     */
    protected $transactions;

    public function __construct(
        Cart $cart,
        CalculatedLineItemCollection $calculatedLineItems,
        CartPrice $price,
        DeliveryCollection $deliveries,
        TransactionCollection $transactions = null
    ) {
        $this->cart = $cart;
        $this->calculatedLineItems = $calculatedLineItems;
        $this->price = $price;
        $this->deliveries = $deliveries;
        $this->transactions = $transactions ?? new TransactionCollection();
    }

    public function getName(): string
    {
        return $this->cart->getName();
    }

    public function getToken(): string
    {
        return $this->cart->getToken();
    }

    public function getPrice(): CartPrice
    {
        return clone $this->price;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getCalculatedLineItems(): CalculatedLineItemCollection
    {
        return $this->calculatedLineItems;
    }

    public function getDeliveries(): DeliveryCollection
    {
        return $this->deliveries;
    }

    public function getErrors(): ErrorCollection
    {
        return $this->cart->getErrors();
    }

    public function clearErrors(): ErrorCollection
    {
        return $this->cart->clearErrors();
    }

    public function getShippingCosts(): CalculatedPrice
    {
        return $this->getDeliveries()->getShippingCosts()->sum();
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['shippingCosts'] = $this->getShippingCosts();

        return $data;
    }

    public function getTransactions(): TransactionCollection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): void
    {
        $this->transactions->add($transaction);
    }
}
