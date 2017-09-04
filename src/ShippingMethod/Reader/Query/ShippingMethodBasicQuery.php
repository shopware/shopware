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

class ShippingMethodBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('shipping_method', 'shippingMethod');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'shippingMethod.uuid as _array_key_',
            'shippingMethod.id as __shippingMethod_id',
            'shippingMethod.uuid as __shippingMethod_uuid',
            'shippingMethod.name as __shippingMethod_name',
            'shippingMethod.type as __shippingMethod_type',
            'shippingMethod.description as __shippingMethod_description',
            'shippingMethod.comment as __shippingMethod_comment',
            'shippingMethod.active as __shippingMethod_active',
            'shippingMethod.position as __shippingMethod_position',
            'shippingMethod.calculation as __shippingMethod_calculation',
            'shippingMethod.surcharge_calculation as __shippingMethod_surcharge_calculation',
            'shippingMethod.tax_calculation as __shippingMethod_tax_calculation',
            'shippingMethod.shipping_free as __shippingMethod_shipping_free',
            'shippingMethod.shop_id as __shippingMethod_shop_id',
            'shippingMethod.shop_uuid as __shippingMethod_shop_uuid',
            'shippingMethod.customer_group_id as __shippingMethod_customer_group_id',
            'shippingMethod.customer_group_uuid as __shippingMethod_customer_group_uuid',
            'shippingMethod.bind_shippingfree as __shippingMethod_bind_shippingfree',
            'shippingMethod.bind_time_from as __shippingMethod_bind_time_from',
            'shippingMethod.bind_time_to as __shippingMethod_bind_time_to',
            'shippingMethod.bind_instock as __shippingMethod_bind_instock',
            'shippingMethod.bind_laststock as __shippingMethod_bind_laststock',
            'shippingMethod.bind_weekday_from as __shippingMethod_bind_weekday_from',
            'shippingMethod.bind_weekday_to as __shippingMethod_bind_weekday_to',
            'shippingMethod.bind_weight_from as __shippingMethod_bind_weight_from',
            'shippingMethod.bind_weight_to as __shippingMethod_bind_weight_to',
            'shippingMethod.bind_price_from as __shippingMethod_bind_price_from',
            'shippingMethod.bind_price_to as __shippingMethod_bind_price_to',
            'shippingMethod.bind_sql as __shippingMethod_bind_sql',
            'shippingMethod.status_link as __shippingMethod_status_link',
            'shippingMethod.calculation_sql as __shippingMethod_calculation_sql',
        ]);

        //$query->leftJoin('shippingMethod', 'shippingMethod_translation', 'shippingMethodTranslation', 'shippingMethod.uuid = shippingMethodTranslation.shippingMethod_uuid AND shippingMethodTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
