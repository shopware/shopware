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

namespace Shopware\Cart\Delivery;

use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\LineItem\DeliverableLineItemInterface;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

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
        StorefrontContext $context
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
