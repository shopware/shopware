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

use Shopware\Cart\Cart\CartCollectorInterface;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\CartBridge\Product\Struct\ProductFetchDefinition;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class ProductCartCollector implements CartCollectorInterface
{
    /**
     * @var ProductGatewayInterface
     */
    private $productGateway;

    public function __construct(ProductGatewayInterface $productGateway)
    {
        $this->productGateway = $productGateway;
    }

    public function prepare(StructCollection $fetchDefinition, Cart $cart, StorefrontContext $context): void
    {
        $lineItems = $cart->getLineItems()->filterType(ProductProcessor::TYPE_PRODUCT);
        if ($lineItems->count() === 0) {
            return;
        }

        $payloads = $lineItems->getPayload();
        $identifiers = array_column($payloads, 'id');

        $fetchDefinition->add(new ProductFetchDefinition($identifiers));
    }

    public function fetch(StructCollection $dataCollection, StructCollection $fetchCollection, StorefrontContext $context): void
    {
        $definitions = $fetchCollection->filterInstance(ProductFetchDefinition::class);
        if ($definitions->count() === 0) {
            return;
        }

        $numbers = [];
        /** @var ProductFetchDefinition[] $definitions */
        foreach ($definitions as $definition) {
            $numbers = array_merge($numbers, $definition->getNumbers());
        }

        $numbers = array_keys(array_flip($numbers));
        $products = $this->productGateway->get($numbers, $context);
        $dataCollection->fill($products->getElements());
    }
}
