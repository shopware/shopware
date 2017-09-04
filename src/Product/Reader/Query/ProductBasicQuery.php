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

namespace Shopware\Product\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetail\Reader\Query\ProductDetailBasicQuery;
use Shopware\ProductManufacturer\Reader\Query\ProductManufacturerBasicQuery;
use Shopware\SeoUrl\Reader\Query\SeoUrlBasicQuery;
use Shopware\Storefront\DetailPage\DetailPageUrlGenerator;
use Shopware\Tax\Reader\Query\TaxBasicQuery;

class ProductBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('product', 'product');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'product.uuid as _array_key_',
                'product.uuid as __product_uuid',
                'product.product_manufacturer_uuid as __product_product_manufacturer_uuid',
                'product.shipping_time as __product_shipping_time',
                'product.created_at as __product_created_at',
                'product.active as __product_active',
                'product.tax_uuid as __product_tax_uuid',
                'product.main_detail_uuid as __product_main_detail_uuid',
                'product.pseudo_sales as __product_pseudo_sales',
                'product.topseller as __product_topseller',
                'product.updated_at as __product_updated_at',
                'product.price_group_id as __product_price_group_id',
                'product.filter_group_uuid as __product_filter_group_uuid',
                'product.last_stock as __product_last_stock',
                'product.notification as __product_notification',
                'product.template as __product_template',
                'product.mode as __product_mode',
                'product.available_from as __product_available_from',
                'product.available_to as __product_available_to',
                'product.configurator_set_id as __product_configurator_set_id',
            ]
        );

        //$query->leftJoin('product', 'product_translation', 'productTranslation', 'product.uuid = productTranslation.product_uuid AND productTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());

        $query->leftJoin('product', 'product_manufacturer', 'productManufacturer', 'productManufacturer.uuid = product.product_manufacturer_uuid');
        ProductManufacturerBasicQuery::addRequirements($query, $context);

        $query->leftJoin('product', 'product_detail', 'productDetail', 'product.main_detail_uuid = productDetail.uuid');
        ProductDetailBasicQuery::addRequirements($query, $context);

        $query->leftJoin('product', 'tax', 'tax', 'tax.uuid = product.tax_uuid');
        TaxBasicQuery::addRequirements($query, $context);

        $query->leftJoin('product', 'seo_url', 'seoUrl', 'product.uuid = seoUrl.foreign_key AND seoUrl.is_canonical = 1 AND seoUrl.shop_uuid = :shopUuid AND seoUrl.name = :productSeoUrlName');
        SeoUrlBasicQuery::addRequirements($query, $context);

        $query->setParameter(':shopUuid', $context->getShopUuid());
        $query->setParameter(':productSeoUrlName', DetailPageUrlGenerator::ROUTE_NAME);

    }
}
