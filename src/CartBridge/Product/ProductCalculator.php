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

namespace Shopware\CartBridge\Product;

use Shopware\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\CartBridge\Product\Struct\CalculatedProduct;
use Shopware\Cart\Tax\Struct\TaxRule;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\ProductPrice\Struct\ProductPriceBasicStruct;

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
        StructCollection $dataCollection
    ): CalculatedLineItemCollection {

        $products = new CalculatedLineItemCollection();

        /** @var LineItemInterface $lineItem */
        foreach ($collection as $lineItem) {
            if (!$dataCollection->has($lineItem->getIdentifier())) {
                continue;
            }

            /** @var ProductBasicStruct $product */
            $product = $dataCollection->get($lineItem->getIdentifier());

            $priceDefinition = $lineItem->getPriceDefinition();
            if (!$priceDefinition) {
                $priceDefinition = $this->getQuantityPrice($lineItem->getQuantity(), $product);
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
                    $product->getStock(),
                    $product->getWeight(),
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

    private function getQuantityPrice(int $quantity, ProductBasicStruct $product): ?PriceDefinition
    {
        $product->getPrices()->sort(
            function(ProductPriceBasicStruct $a, ProductPriceBasicStruct $b) {
                return $a->getQuantityStart() < $b->getQuantityStart();
            }
        );

        foreach ($product->getPrices() as $price) {
            if ($price->getQuantityStart() <= $quantity) {
                return new PriceDefinition(
                    $price->getPrice(),
                    new TaxRuleCollection([
                        new TaxRule($product->getTax()->getRate())
                    ]),
                    $quantity
                );
            }
        }

        return null;
    }
}
