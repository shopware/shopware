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

use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductPriceBasicCollection;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Context\Struct\ShopContext;

class ProductGateway implements ProductGatewayInterface
{
    /**
     * @var ProductRepository
     */
    private $repository;

    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }

    public function get(array $numbers, ShopContext $context): ProductBasicCollection
    {
        $products = $this->repository->readBasic(
            $numbers,
            $context->getTranslationContext()
        );

        foreach ($products as $product) {
            $product->setPrices(
                $this->filterCustomerGroupPrices($product, $context)
            );
        }

        return $products;
    }

    private function filterCustomerGroupPrices(ProductBasicStruct $product, ShopContext $context): ProductPriceBasicCollection
    {
        $customerPrices = $product->getPrices()->filterByCustomerGroupId(
            $context->getCurrentCustomerGroup()->getId()
        );

        if ($customerPrices->count() > 0) {
            return $customerPrices;
        }

        return $product->getPrices()->filterByCustomerGroupId(
            $context->getFallbackCustomerGroup()->getId()
        );
    }
}
