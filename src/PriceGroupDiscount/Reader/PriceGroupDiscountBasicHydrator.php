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

namespace Shopware\PriceGroupDiscount\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicStruct;

class PriceGroupDiscountBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): PriceGroupDiscountBasicStruct
    {
        $priceGroupDiscount = new PriceGroupDiscountBasicStruct();

        $priceGroupDiscount->setId((int) $data['__priceGroupDiscount_id']);
        $priceGroupDiscount->setUuid((string) $data['__priceGroupDiscount_uuid']);
        $priceGroupDiscount->setPriceGroupId((int) $data['__priceGroupDiscount_price_group_id']);
        $priceGroupDiscount->setPriceGroupUuid((string) $data['__priceGroupDiscount_price_group_uuid']);
        $priceGroupDiscount->setCustomerGroupId((int) $data['__priceGroupDiscount_customer_group_id']);
        $priceGroupDiscount->setCustomerGroupUuid((string) $data['__priceGroupDiscount_customer_group_uuid']);
        $priceGroupDiscount->setDiscount((float) $data['__priceGroupDiscount_discount']);
        $priceGroupDiscount->setDiscountStart((float) $data['__priceGroupDiscount_discount_start']);

        return $priceGroupDiscount;
    }
}
