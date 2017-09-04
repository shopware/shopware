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

namespace Shopware\ShippingMethod\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;

class ShippingMethodBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): ShippingMethodBasicStruct
    {
        $shippingMethod = new ShippingMethodBasicStruct();

        $shippingMethod->setId((int)$data['__shippingMethod_id']);
        $shippingMethod->setUuid((string)$data['__shippingMethod_uuid']);
        $shippingMethod->setName((string)$data['__shippingMethod_name']);
        $shippingMethod->setType((int)$data['__shippingMethod_type']);
        $shippingMethod->setDescription((string)$data['__shippingMethod_description']);
        $shippingMethod->setComment((string)$data['__shippingMethod_comment']);
        $shippingMethod->setActive((bool)$data['__shippingMethod_active']);
        $shippingMethod->setPosition((int)$data['__shippingMethod_position']);
        $shippingMethod->setCalculation((int)$data['__shippingMethod_calculation']);
        $shippingMethod->setSurchargeCalculation((int)$data['__shippingMethod_surcharge_calculation']);
        $shippingMethod->setTaxCalculation((int)$data['__shippingMethod_tax_calculation']);
        $shippingMethod->setShippingFree(
            isset($data['__shippingMethod_shipping_free']) ? (float)$data['__shippingMethod_shipping_free'] : null
        );
        $shippingMethod->setShopId(
            isset($data['__shippingMethod_shop_id']) ? (int)$data['__shippingMethod_shop_id'] : null
        );
        $shippingMethod->setShopUuid(
            isset($data['__shippingMethod_shop_uuid']) ? (string)$data['__shippingMethod_shop_uuid'] : null
        );
        $shippingMethod->setCustomerGroupId(
            isset($data['__shippingMethod_customer_group_id']) ? (int)$data['__shippingMethod_customer_group_id'] : null
        );
        $shippingMethod->setCustomerGroupUuid(
            isset($data['__shippingMethod_customer_group_uuid']) ? (string)$data['__shippingMethod_customer_group_uuid'] : null
        );
        $shippingMethod->setBindShippingfree((int)$data['__shippingMethod_bind_shippingfree']);
        $shippingMethod->setBindTimeFrom(
            isset($data['__shippingMethod_bind_time_from']) ? (int)$data['__shippingMethod_bind_time_from'] : null
        );
        $shippingMethod->setBindTimeTo(
            isset($data['__shippingMethod_bind_time_to']) ? (int)$data['__shippingMethod_bind_time_to'] : null
        );
        $shippingMethod->setBindInstock(
            isset($data['__shippingMethod_bind_instock']) ? (int)$data['__shippingMethod_bind_instock'] : null
        );
        $shippingMethod->setBindLaststock((int)$data['__shippingMethod_bind_laststock']);
        $shippingMethod->setBindWeekdayFrom(
            isset($data['__shippingMethod_bind_weekday_from']) ? (int)$data['__shippingMethod_bind_weekday_from'] : null
        );
        $shippingMethod->setBindWeekdayTo(
            isset($data['__shippingMethod_bind_weekday_to']) ? (int)$data['__shippingMethod_bind_weekday_to'] : null
        );
        $shippingMethod->setBindWeightFrom(
            isset($data['__shippingMethod_bind_weight_from']) ? (float)$data['__shippingMethod_bind_weight_from'] : null
        );
        $shippingMethod->setBindWeightTo(
            isset($data['__shippingMethod_bind_weight_to']) ? (float)$data['__shippingMethod_bind_weight_to'] : null
        );
        $shippingMethod->setBindPriceFrom(
            isset($data['__shippingMethod_bind_price_from']) ? (float)$data['__shippingMethod_bind_price_from'] : null
        );
        $shippingMethod->setBindPriceTo(
            isset($data['__shippingMethod_bind_price_to']) ? (float)$data['__shippingMethod_bind_price_to'] : null
        );
        $shippingMethod->setBindSql(
            isset($data['__shippingMethod_bind_sql']) ? (string)$data['__shippingMethod_bind_sql'] : null
        );
        $shippingMethod->setStatusLink(
            isset($data['__shippingMethod_status_link']) ? (string)$data['__shippingMethod_status_link'] : null
        );
        $shippingMethod->setCalculationSql(
            isset($data['__shippingMethod_calculation_sql']) ? (string)$data['__shippingMethod_calculation_sql'] : null
        );

        return $shippingMethod;
    }
}
