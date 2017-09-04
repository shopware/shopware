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
use Shopware\AreaCountry\Reader\Query\AreaCountryBasicQuery;
use Shopware\Category\Reader\Query\CategoryBasicQuery;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Reader\Query\CustomerGroupBasicQuery;
use Shopware\PaymentMethod\Reader\Query\PaymentMethodBasicQuery;
use Shopware\ShippingMethod\Reader\Query\ShippingMethodBasicQuery;
use Shopware\ShopTemplate\Reader\Query\ShopTemplateBasicQuery;

class ShopDetailQuery extends ShopBasicQuery
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection, $context);
        self::internalRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        parent::addRequirements($query, $context);
        self::internalRequirements($query, $context);
    }

    private static function internalRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->leftJoin('shop', 'category', 'category', 'category.uuid = shop.category_uuid');
        CategoryBasicQuery::addRequirements($query, $context);

        $query->leftJoin(
            'shop',
            'shipping_method',
            'shippingMethod',
            'shippingMethod.uuid = shop.shipping_method_uuid'
        );
        ShippingMethodBasicQuery::addRequirements($query, $context);

        $query->leftJoin('shop', 'shop_template', 'shopTemplate', 'shopTemplate.uuid = shop.shop_template_uuid');
        ShopTemplateBasicQuery::addRequirements($query, $context);

        $query->leftJoin('shop', 'area_country', 'areaCountry', 'areaCountry.uuid = shop.area_country_uuid');
        AreaCountryBasicQuery::addRequirements($query, $context);

        $query->leftJoin('shop', 'payment_method', 'paymentMethod', 'paymentMethod.uuid = shop.payment_method_uuid');
        PaymentMethodBasicQuery::addRequirements($query, $context);

        $query->leftJoin('shop', 'customer_group', 'customerGroup', 'customerGroup.uuid = shop.customer_group_uuid');
        CustomerGroupBasicQuery::addRequirements($query, $context);

        $query->addSelect(
            '
            (
                SELECT GROUP_CONCAT(mapping.currency_uuid SEPARATOR \'|\') FROM
                shop_currency mapping
                WHERE shop.uuid = mapping.currency_uuid
            ) as __shop_currency_uuids
        '
        );
    }
}
