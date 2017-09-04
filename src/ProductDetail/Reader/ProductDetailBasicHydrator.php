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

namespace Shopware\ProductDetail\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;
use Shopware\Unit\Reader\UnitBasicHydrator;

class ProductDetailBasicHydrator extends Hydrator
{
    /**
     * @var UnitBasicHydrator
     */
    private $unitBasicHydrator;

    public function __construct(UnitBasicHydrator $unitBasicHydrator)
    {
        $this->unitBasicHydrator = $unitBasicHydrator;
    }

    public function hydrate(array $data): ProductDetailBasicStruct
    {
        $productDetail = new ProductDetailBasicStruct();

        $productDetail->setId((int)$data['__productDetail_id']);
        $productDetail->setUuid((string)$data['__productDetail_uuid']);
        $productDetail->setProductUuid((string)$data['__productDetail_product_uuid']);
        $productDetail->setSupplierNumber(
            isset($data['__productDetail_supplier_number']) ? (string)$data['__productDetail_supplier_number'] : null
        );
        $productDetail->setIsMain((bool)$data['__productDetail_is_main']);
        $productDetail->setAdditionalText(
            isset($data['__productDetail_additional_text']) ? (string)$data['__productDetail_additional_text'] : null
        );
        $productDetail->setSales((int)$data['__productDetail_sales']);
        $productDetail->setActive((bool)$data['__productDetail_active']);
        $productDetail->setStock(isset($data['__productDetail_stock']) ? (int)$data['__productDetail_stock'] : null);
        $productDetail->setStockmin(
            isset($data['__productDetail_stockmin']) ? (int)$data['__productDetail_stockmin'] : null
        );
        $productDetail->setWeight(
            isset($data['__productDetail_weight']) ? (float)$data['__productDetail_weight'] : null
        );
        $productDetail->setPosition((int)$data['__productDetail_position']);
        $productDetail->setWidth(isset($data['__productDetail_width']) ? (float)$data['__productDetail_width'] : null);
        $productDetail->setHeight(
            isset($data['__productDetail_height']) ? (float)$data['__productDetail_height'] : null
        );
        $productDetail->setLength(
            isset($data['__productDetail_length']) ? (float)$data['__productDetail_length'] : null
        );
        $productDetail->setEan(isset($data['__productDetail_ean']) ? (string)$data['__productDetail_ean'] : null);
        $productDetail->setUnitId(
            isset($data['__productDetail_unit_id']) ? (int)$data['__productDetail_unit_id'] : null
        );
        $productDetail->setUnitUuid(
            isset($data['__productDetail_unit_uuid']) ? (string)$data['__productDetail_unit_uuid'] : null
        );
        $productDetail->setPurchaseSteps(
            isset($data['__productDetail_purchase_steps']) ? (int)$data['__productDetail_purchase_steps'] : null
        );
        $productDetail->setMaxPurchase(
            isset($data['__productDetail_max_purchase']) ? (int)$data['__productDetail_max_purchase'] : null
        );
        $productDetail->setMinPurchase((int)$data['__productDetail_min_purchase']);
        $productDetail->setPurchaseUnit(
            isset($data['__productDetail_purchase_unit']) ? (float)$data['__productDetail_purchase_unit'] : null
        );
        $productDetail->setReferenceUnit(
            isset($data['__productDetail_reference_unit']) ? (float)$data['__productDetail_reference_unit'] : null
        );
        $productDetail->setPackUnit(
            isset($data['__productDetail_pack_unit']) ? (string)$data['__productDetail_pack_unit'] : null
        );
        $productDetail->setReleaseDate(
            isset($data['__productDetail_release_date']) ? new \DateTime($data['__productDetail_release_date']) : null
        );
        $productDetail->setShippingFree((int)$data['__productDetail_shipping_free']);
        $productDetail->setShippingTime(
            isset($data['__productDetail_shipping_time']) ? (string)$data['__productDetail_shipping_time'] : null
        );
        $productDetail->setPurchasePrice((float)$data['__productDetail_purchase_price']);
        $productDetail->setUnit($this->unitBasicHydrator->hydrate($data));

        return $productDetail;
    }
}
