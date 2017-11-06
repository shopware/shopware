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

use Doctrine\DBAL\Connection;
use Shopware\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Cart\Delivery\DeliveryInformation;
use Shopware\Cart\Product\ProductDataCollection;
use Shopware\Cart\Product\ProductGatewayInterface;
use Shopware\Context\Struct\ShopContext;
use Shopware\ProductDetail\Repository\ProductDetailRepository;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;

class ProductGateway implements ProductGatewayInterface
{
    /**
     * @var ProductDetailRepository
     */
    private $repository;

    public function __construct(ProductDetailRepository $repository)
    {
        $this->repository = $repository;
    }

    public function get(array $numbers, ShopContext $context): ProductDetailBasicCollection
    {
        $details = $this->repository->readBasic(
            $numbers,
            $context->getTranslationContext()
        );

        foreach ($details as $detail) {
            $detail->setPrices(
                $this->filterCustomerGroupPrices($detail, $context)
            );
        }

        return $details;
    }

    private function filterCustomerGroupPrices(ProductDetailBasicStruct $detail, ShopContext $context): ProductDetailPriceBasicCollection
    {
        $customerPrices = $detail->getPrices()->filterByCustomerGroupUuid(
            $context->getCurrentCustomerGroup()->getUuid()
        );

        if ($customerPrices->count() > 0) {
            return $customerPrices;
        }

        return $detail->getPrices()->filterByCustomerGroupUuid(
            $context->getFallbackCustomerGroup()->getUuid()
        );
    }
}
