<?php declare(strict_types=1);
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

namespace Shopware\ProductPrice\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\ProductPrice\Struct\ProductPriceBasicStruct;

class ProductPriceBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): ProductPriceBasicStruct
    {
        $productPrice = new ProductPriceBasicStruct();

        $productPrice->setId((int)$data['__productPrice_id']);
        $productPrice->setUuid((string)$data['__productPrice_uuid']);
        $productPrice->setPricegroup((string)$data['__productPrice_pricegroup']);
        $productPrice->setFrom((int)$data['__productPrice_from']);
        $productPrice->setTo(isset($data['__productPrice_to']) ? (int)$data['__productPrice_to'] : null);
        $productPrice->setProductId((int)$data['__productPrice_product_id']);
        $productPrice->setProductUuid((string)$data['__productPrice_product_uuid']);
        $productPrice->setProductDetailId((int)$data['__productPrice_product_detail_id']);
        $productPrice->setProductDetailUuid((string)$data['__productPrice_product_detail_uuid']);
        $productPrice->setPrice((float)$data['__productPrice_price']);
        $productPrice->setPseudoprice(
            isset($data['__productPrice_pseudoprice']) ? (float)$data['__productPrice_pseudoprice'] : null
        );
        $productPrice->setBaseprice(
            isset($data['__productPrice_baseprice']) ? (float)$data['__productPrice_baseprice'] : null
        );
        $productPrice->setPercent(
            isset($data['__productPrice_percent']) ? (float)$data['__productPrice_percent'] : null
        );

        return $productPrice;
    }
}
