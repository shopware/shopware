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

namespace Shopware\ShippingMethod\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class ShippingMethodDetailQuery extends ShippingMethodBasicQuery
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
        $query->addSelect('
            (
                SELECT GROUP_CONCAT(mapping.area_country_uuid SEPARATOR \'|\') FROM
                shipping_method_country mapping
                WHERE shippingMethod.uuid = mapping.uuid
            ) as __area_country_uuids
        ');
        $query->addSelect('
            (
                SELECT GROUP_CONCAT(mapping.category_uuid SEPARATOR \'|\') FROM
                shipping_method_category mapping
                WHERE shippingMethod.uuid = mapping.uuid
            ) as __category_uuids
        ');
        $query->addSelect('
            (
                SELECT GROUP_CONCAT(mapping.holiday_uuid SEPARATOR \'|\') FROM
                shipping_method_holiday mapping
                WHERE shippingMethod.uuid = mapping.uuid
            ) as __holiday_uuids
        ');
        $query->addSelect('
            (
                SELECT GROUP_CONCAT(mapping.payment_method_uuid SEPARATOR \'|\') FROM
                shipping_method_payment_method mapping
                WHERE shippingMethod.uuid = mapping.uuid
            ) as __payment_method_uuids
        ');
    }
}
