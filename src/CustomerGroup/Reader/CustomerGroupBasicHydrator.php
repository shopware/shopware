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

namespace Shopware\CustomerGroup\Reader;

use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Framework\Struct\Hydrator;

class CustomerGroupBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): CustomerGroupBasicStruct
    {
        $customerGroup = new CustomerGroupBasicStruct();

        $customerGroup->setId((int)$data['__customerGroup_id']);
        $customerGroup->setUuid((string)$data['__customerGroup_uuid']);
        $customerGroup->setGroupKey((string)$data['__customerGroup_group_key']);
        $customerGroup->setDescription((string)$data['__customerGroup_description']);
        $customerGroup->setDisplayGrossPrices((bool)$data['__customerGroup_display_gross_prices']);
        $customerGroup->setInputGrossPrices((bool)$data['__customerGroup_input_gross_prices']);
        $customerGroup->setMode((int)$data['__customerGroup_mode']);
        $customerGroup->setDiscount((float)$data['__customerGroup_discount']);
        $customerGroup->setMinimumOrderAmount((float)$data['__customerGroup_minimum_order_amount']);
        $customerGroup->setMinimumOrderAmountSurcharge((float)$data['__customerGroup_minimum_order_amount_surcharge']);

        return $customerGroup;
    }
}
