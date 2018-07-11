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

namespace Shopware\Core\Checkout\Cart\Cart;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Framework\Struct\Struct;

class Cart extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var CartPrice
     */
    protected $price;

    /**
     * @var LineItemCollection
     */
    protected $lineItems;

    /**
     * @var ErrorCollection
     */
    protected $errors;

    /**
     * @var DeliveryCollection
     */
    protected $deliveries;

    /**
     * @var TransactionCollection
     */
    protected $transactions;

    public function __construct(string $name, string $token)
    {
        $this->name = $name;
        $this->token = $token;
        $this->lineItems = new LineItemCollection();
        $this->transactions = new TransactionCollection();
        $this->errors = new ErrorCollection();
        $this->deliveries = new DeliveryCollection();
        $this->price = new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getLineItems(): LineItemCollection
    {
        return $this->lineItems;
    }

    public function setLineItems(LineItemCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getErrors(): ErrorCollection
    {
        return $this->errors;
    }

    public function setErrors(ErrorCollection $errors): void
    {
        $this->errors = $errors;
    }

    public function getDeliveries(): DeliveryCollection
    {
        return $this->deliveries;
    }

    public function setDeliveries(DeliveryCollection $deliveries): void
    {
        $this->deliveries = $deliveries;
    }

    public function addLineItems(LineItemCollection $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $this->add($lineItem);
        }
    }

    public function addDeliveries(DeliveryCollection $deliveries): void
    {
        foreach ($deliveries as $delivery) {
            $this->deliveries->add($delivery);
        }
    }

    public function addErrors(ErrorCollection $errors): void
    {
        foreach ($errors as $error) {
            $this->errors->add($error);
        }
    }

    public function getPrice(): CartPrice
    {
        return $this->price;
    }

    public function setPrice(CartPrice $price): void
    {
        $this->price = $price;
    }

    public function add(LineItem $lineItem): self
    {
        $this->lineItems->add($lineItem);

        return $this;
    }

    public function get(string $lineItemKey)
    {
        return $this->lineItems->get($lineItemKey);
    }

    public function has(string $lineItemKey)
    {
        return $this->lineItems->has($lineItemKey);
    }

    public function getTransactions(): TransactionCollection
    {
        return $this->transactions;
    }

    public function setTransactions(TransactionCollection $transactions): self
    {
        $this->transactions = $transactions;

        return $this;
    }

    public function getShippingCosts(): Price
    {
        return $this->deliveries->getShippingCosts()->sum();
    }
}
