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

namespace Shopware\Cart\Product;

use Shopware\Cart\Delivery\DeliveryDate;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Tax\TaxRule;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\IndexedCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;

class ProductCalculator
{
    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    public function __construct(PriceCalculator $priceCalculator)
    {
        $this->priceCalculator = $priceCalculator;
    }

    public function calculate(
        LineItemCollection $collection,
        ShopContext $context,
        IndexedCollection $dataCollection
    ): CalculatedLineItemCollection {

        $products = new CalculatedLineItemCollection();

        /** @var LineItemInterface $lineItem */
        foreach ($collection as $lineItem) {
            if (!$dataCollection->has($lineItem->getIdentifier())) {
                continue;
            }

            /** @var ProductDetailBasicStruct $detail */
            $detail = $dataCollection->get($lineItem->getIdentifier());

            $priceDefinition = $lineItem->getPriceDefinition();
            if (!$priceDefinition) {
                $priceDefinition = $this->getQuantityPrice($lineItem->getQuantity(), $detail);
            }

            $priceDefinition = new PriceDefinition(
                $priceDefinition->getPrice(),
                $priceDefinition->getTaxRules(),
                $lineItem->getQuantity(),
                $priceDefinition->isCalculated()
            );

            $price = $this->priceCalculator->calculate($priceDefinition, $context);

            $products->add(
                new CalculatedProduct(
                    $lineItem,
                    $price,
                    $lineItem->getIdentifier(),
                    $lineItem->getQuantity(),
                    $detail->getStock(),
                    $detail->getWeight(),
                    $this->getInstockDeliveryDate(),
                    $this->getOutOfStockDeliveryDate(),
                    null
                )
            );
        }

        return $products;
    }

    private function getInstockDeliveryDate(): DeliveryDate
    {
        return new DeliveryDate(
            (new \DateTime())
                ->add(new \DateInterval('P1D')),
            (new \DateTime())
                ->add(new \DateInterval('P1D'))
                ->add(new \DateInterval('P3D'))
        );
    }

    private function getOutOfStockDeliveryDate(): DeliveryDate
    {
        return new DeliveryDate(
            (new \DateTime())
                ->add(new \DateInterval('P10D'))
                ->add(new \DateInterval('P1D')),
            (new \DateTime())
                ->add(new \DateInterval('P10D'))
                ->add(new \DateInterval('P1D'))
                ->add(new \DateInterval('P3D'))
        );
    }

    private function getQuantityPrice(int $quantity, ProductDetailBasicStruct $detail): ?PriceDefinition
    {
        $detail->getPrices()->sort(
            function(ProductDetailPriceBasicStruct $a, ProductDetailPriceBasicStruct $b) {
                return $a->getQuantityStart() < $b->getQuantityStart();
            }
        );

        foreach ($detail->getPrices() as $price) {
            if ($price->getQuantityStart() <= $quantity) {
                return new PriceDefinition(
                    $price->getPrice(),

                    //todo@dr use taxes of product after product and detail merged
                    new TaxRuleCollection([
                        new TaxRule(19)
                    ]),
                    $quantity
                );
            }
        }

        return null;
    }
}
