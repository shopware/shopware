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

namespace Shopware\Checkout\Cart\Delivery\Struct;

use Shopware\Checkout\Cart\LineItem\DeliverableLineItemInterface;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Framework\Struct\Collection;

class DeliveryCollection extends Collection
{
    /**
     * @var Delivery[]
     */
    protected $elements = [];

    public function add(Delivery $delivery): void
    {
        parent::doAdd($delivery);
    }

    public function remove(string $key): void
    {
        parent::doRemoveByKey($key);
    }

    /**
     * Sorts the delivery collection by earliest delivery date
     */
    public function sortDeliveries(): self
    {
        $this->sort(function (Delivery $a, Delivery $b) {
            if ($a->getLocation() !== $b->getLocation()) {
                return -1;
            }

            return $a->getDeliveryDate()->getEarliest() > $b->getDeliveryDate()->getEarliest();
        });

        return $this;
    }

    public function getDelivery(DeliveryDate $deliveryDate, ShippingLocation $location): ? Delivery
    {
        foreach ($this->elements as $delivery) {
            if ($delivery->getDeliveryDate()->getEarliest() != $deliveryDate->getEarliest()) {
                continue;
            }
            if ($delivery->getDeliveryDate()->getLatest() != $deliveryDate->getLatest()) {
                continue;
            }

            if ($delivery->getLocation() != $location) {
                continue;
            }

            return $delivery;
        }

        return null;
    }

    /**
     * @param DeliverableLineItemInterface $item
     *
     * @return bool
     */
    public function contains(DeliverableLineItemInterface $item): bool
    {
        foreach ($this->elements as $delivery) {
            if ($delivery->getPositions()->has($item->getIdentifier())) {
                return true;
            }
        }

        return false;
    }

    public function getShippingCosts(): CalculatedPriceCollection
    {
        return new CalculatedPriceCollection(
            $this->map(function (Delivery $delivery) {
                return $delivery->getShippingCosts();
            })
        );
    }
}
