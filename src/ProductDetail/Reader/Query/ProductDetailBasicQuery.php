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

namespace Shopware\ProductDetail\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Unit\Reader\Query\UnitBasicQuery;

class ProductDetailBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('product_detail', 'productDetail');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'productDetail.uuid as _array_key_',
            'productDetail.id as __productDetail_id',
            'productDetail.uuid as __productDetail_uuid',
            'productDetail.product_id as __productDetail_product_id',
            'productDetail.product_uuid as __productDetail_product_uuid',
            'productDetail.order_number as __productDetail_order_number',
            'productDetail.supplier_number as __productDetail_supplier_number',
            'productDetail.is_main as __productDetail_is_main',
            'productDetail.additional_text as __productDetail_additional_text',
            'productDetail.sales as __productDetail_sales',
            'productDetail.active as __productDetail_active',
            'productDetail.stock as __productDetail_stock',
            'productDetail.stockmin as __productDetail_stockmin',
            'productDetail.weight as __productDetail_weight',
            'productDetail.position as __productDetail_position',
            'productDetail.width as __productDetail_width',
            'productDetail.height as __productDetail_height',
            'productDetail.length as __productDetail_length',
            'productDetail.ean as __productDetail_ean',
            'productDetail.unit_id as __productDetail_unit_id',
            'productDetail.unit_uuid as __productDetail_unit_uuid',
            'productDetail.purchase_steps as __productDetail_purchase_steps',
            'productDetail.max_purchase as __productDetail_max_purchase',
            'productDetail.min_purchase as __productDetail_min_purchase',
            'productDetail.purchase_unit as __productDetail_purchase_unit',
            'productDetail.reference_unit as __productDetail_reference_unit',
            'productDetail.pack_unit as __productDetail_pack_unit',
            'productDetail.release_date as __productDetail_release_date',
            'productDetail.shipping_free as __productDetail_shipping_free',
            'productDetail.shipping_time as __productDetail_shipping_time',
            'productDetail.purchase_price as __productDetail_purchase_price',
        ]);

        //$query->leftJoin('productDetail', 'productDetail_translation', 'productDetailTranslation', 'productDetail.uuid = productDetailTranslation.productDetail_uuid AND productDetailTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());

        $query->leftJoin('productDetail', 'unit', 'unit', 'unit.uuid = productDetail.unit_uuid');
        UnitBasicQuery::addRequirements($query, $context);
    }
}
