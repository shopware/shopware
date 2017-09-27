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

use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\ProcessorCart;
use Shopware\Cart\LineItem\DeliverableLineItemInterface;
use Shopware\Context\Struct\ShopContext;
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
        CartContainer $cartContainer,
        ProcessorCart $processorCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void {
        $items = $processorCart
            ->getCalculatedLineItems()
            ->filterInstance(DeliverableLineItemInterface::class);

        $items = $items->filter(function (DeliverableLineItemInterface $lineItem) {
            return null === $lineItem->getDelivery();
        });

        if (0 === $items->count()) {
            return;
        }

        $deliveries = $this->stockDeliverySeparator->addItemsToDeliveries(
            $processorCart->getDeliveries(),
            $items,
            $context
        );

        $deliveries->sortDeliveries();

        $processorCart->getDeliveries()->clear();
        $processorCart->getDeliveries()->fill($deliveries->getIterator()->getArrayCopy());
    }
}
