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

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Checkout\Cart\LineItem\DeliverableLineItemInterface;
use Shopware\Core\Framework\Struct\StructCollection;

class DeliverySeparatorProcessor implements CartProcessorInterface
{
    /**
     * @var StockDeliverySeparator
     */
    private $stockDeliverySeparator;

    public function __construct(StockDeliverySeparator $stockDeliverySeparator)
    {
        $this->stockDeliverySeparator = $stockDeliverySeparator;
    }

    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        CustomerContext $context
    ): void {
        $items = $calculatedCart
            ->getCalculatedLineItems()
            ->filterInstance(DeliverableLineItemInterface::class);

        $items = $items->filter(function (DeliverableLineItemInterface $lineItem) {
            return $lineItem->getDelivery() === null;
        });

        if ($items->count() === 0) {
            return;
        }

        $this->stockDeliverySeparator->addItemsToDeliveries(
            $calculatedCart->getDeliveries(),
            $items,
            $context
        );
    }
}
