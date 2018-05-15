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

namespace Shopware\Checkout\CartBridge\Product;

use Shopware\Content\Product\Struct\ProductBasicStruct;
use Shopware\Content\Product\Struct\ProductServiceBasicStruct;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Checkout\Cart\LineItem\LineItemInterface;
use Shopware\Checkout\Cart\Price\PriceCalculator;
use Shopware\Checkout\Cart\Price\Struct\PriceDefinition;
use Shopware\Checkout\CartBridge\Product\Struct\CalculatedProduct;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

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
        StorefrontContext $context,
        StructCollection $dataCollection
    ): CalculatedLineItemCollection {
        $products = new CalculatedLineItemCollection();

        /** @var LineItemInterface $lineItem */
        foreach ($collection as $lineItem) {
            $payload = $lineItem->getPayload();
            $identifier = $payload['id'];

            $serviceIds = [];
            if (array_key_exists('services', $payload) && !empty($payload['services'])) {
                $serviceIds = $payload['services'];
            }
            if (!$dataCollection->has($identifier)) {
                continue;
            }

            /** @var ProductBasicStruct $product */
            $product = $dataCollection->get($identifier);

            $priceDefinition = $lineItem->getPriceDefinition();
            if (!$priceDefinition) {
                $priceDefinition = $product->getPriceDefinitionForQuantity(
                    $context->getApplicationContext(),
                    $lineItem->getQuantity()
                );
            }

            $priceDefinition = new PriceDefinition(
                $priceDefinition->getPrice(),
                $priceDefinition->getTaxRules(),
                $lineItem->getQuantity(),
                $priceDefinition->isCalculated()
            );

            $price = $this->priceCalculator->calculate($priceDefinition, $context);

            $calculatedProduct = new CalculatedProduct(
                $lineItem,
                $price,
                $lineItem->getIdentifier(),
                $lineItem->getQuantity(),
                $product->getDeliveryDate(),
                $product->getRestockDeliveryDate(),
                $product
            );

            foreach ($serviceIds as $serviceId) {
                if (!$dataCollection->has($serviceId)) {
                    continue;
                }

                /** @var ProductServiceBasicStruct $service */
                $service = $dataCollection->get($serviceId);
                if (!$service) {
                    continue;
                }

                $priceDefinition = $service->getPriceDefinition($lineItem->getQuantity(), $context->getApplicationContext());
                $price = $this->priceCalculator->calculate($priceDefinition, $context);

                $calculatedProduct->addChild(
                    new CalculatedLineItem(
                        $service->getId(),
                        $price,
                        $price->getQuantity(),
                        'service',
                        $service->getOption()->getName()
                    )
                );
            }

            $products->add($calculatedProduct);
        }

        return $products;
    }
}
