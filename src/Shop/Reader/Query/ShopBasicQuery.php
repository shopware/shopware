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

namespace Shopware\Shop\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Reader\Query\CurrencyBasicQuery;
use Shopware\Locale\Reader\Query\LocaleBasicQuery;

class ShopBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('shop', 'shop');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'shop.uuid as _array_key_',
            'shop.id as __shop_id',
            'shop.uuid as __shop_uuid',
            'shop.main_id as __shop_main_id',
            'shop.name as __shop_name',
            'shop.title as __shop_title',
            'shop.position as __shop_position',
            'shop.hosts as __shop_hosts',
            'shop.secure as __shop_secure',
            'shop.shop_template_id as __shop_shop_template_id',
            'shop.document_template_id as __shop_document_template_id',
            'shop.category_id as __shop_category_id',
            'shop.locale_id as __shop_locale_id',
            'shop.currency_id as __shop_currency_id',
            'shop.customer_group_id as __shop_customer_group_id',
            'shop.fallback_id as __shop_fallback_id',
            'shop.customer_scope as __shop_customer_scope',
            'shop.is_default as __shop_is_default',
            'shop.active as __shop_active',
            'shop.payment_method_id as __shop_payment_method_id',
            'shop.shipping_method_id as __shop_shipping_method_id',
            'shop.area_country_id as __shop_area_country_id',
            'shop.main_uuid as __shop_main_uuid',
            'shop.category_uuid as __shop_category_uuid',
            'shop.locale_uuid as __shop_locale_uuid',
            'shop.currency_uuid as __shop_currency_uuid',
            'shop.customer_group_uuid as __shop_customer_group_uuid',
            'shop.fallback_locale_uuid as __shop_fallback_locale_uuid',

            'main.payment_method_uuid as __shop_payment_method_uuid',
            'main.shipping_method_uuid as __shop_shipping_method_uuid',
            'main.area_country_uuid as __shop_area_country_uuid',
            'main.host as __shop_host',
            'main.base_path as __shop_base_path',
            'main.base_url as __shop_base_url',
            'main.shop_template_uuid as __shop_shop_template_uuid',
            'main.document_template_uuid as __shop_document_template_uuid',
            'main.tax_calculation_type as __shop_tax_calculation_type',
        ]);

        //$query->leftJoin('shop', 'shop_translation', 'shopTranslation', 'shop.uuid = shopTranslation.shop_uuid AND shopTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
        $query->innerJoin('shop', 'shop', 'main', 'IFNULL(shop.main_id, shop.id) = main.id');

        $query->leftJoin('shop', 'currency', 'currency', 'currency.uuid = shop.currency_uuid');
        CurrencyBasicQuery::addRequirements($query, $context);

        $query->leftJoin('shop', 'locale', 'locale', 'locale.uuid = shop.locale_uuid');
        LocaleBasicQuery::addRequirements($query, $context);
    }
}
