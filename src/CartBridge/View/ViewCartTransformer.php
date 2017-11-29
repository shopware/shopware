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

namespace Shopware\CartBridge\View;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class ViewCartTransformer
{
    /**
     * @var ViewLineItemTransformerInterface[]
     */
    private $transformers;

    public function __construct(iterable $transformers)
    {
        $this->transformers = $transformers;
    }

    public function transform(CalculatedCart $calculatedCart, ShopContext $context): ViewCart
    {
        $viewCart = new ViewCart($calculatedCart);

        $dataCollection = $this->prepare($calculatedCart, $context);

        foreach ($this->transformers as $transformer) {
            $transformer->transform($calculatedCart, $viewCart, $context, $dataCollection);
        }

        $viewCart->getViewLineItems()->sortByIdentifiers(
            $calculatedCart->getCalculatedLineItems()->getIdentifiers()
        );

        /** @var \Shopware\Cart\Delivery\Struct\Delivery $delivery */
        foreach ($calculatedCart->getDeliveries() as $delivery) {
            $positions = new ViewDeliveryPositionCollection();

            foreach ($delivery->getPositions() as $deliveryPosition) {
                $positions->add(
                    new ViewDeliveryPosition(
                        $viewCart->getViewLineItems()->get($deliveryPosition->getIdentifier()),
                        $deliveryPosition
                    )
                );
            }

            $viewCart->getDeliveries()->add(
                new ViewDelivery($delivery, $positions)
            );
        }

        return $viewCart;
    }

    private function prepare(CalculatedCart $calculatedCart, ShopContext $context): StructCollection
    {
        $fetchDefinitions = new StructCollection();
        foreach ($this->transformers as $transformer) {
            $transformer->prepare($fetchDefinitions, $calculatedCart, $context);
        }

        $dataCollection = new StructCollection();
        foreach ($this->transformers as $transformer) {
            $transformer->fetch($dataCollection, $fetchDefinitions, $context);
        }

        return $dataCollection;
    }
}
