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

namespace Shopware\TaxAreaRule\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicStruct;

class TaxAreaRuleBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): TaxAreaRuleBasicStruct
    {
        $taxAreaRule = new TaxAreaRuleBasicStruct();

        $taxAreaRule->setId((int)$data['__taxAreaRule_id']);
        $taxAreaRule->setUuid((string)$data['__taxAreaRule_uuid']);
        $taxAreaRule->setAreaId(isset($data['__taxAreaRule_area_id']) ? (int)$data['__taxAreaRule_area_id'] : null);
        $taxAreaRule->setAreaUuid(
            isset($data['__taxAreaRule_area_uuid']) ? (string)$data['__taxAreaRule_area_uuid'] : null
        );
        $taxAreaRule->setAreaCountryId(
            isset($data['__taxAreaRule_area_country_id']) ? (int)$data['__taxAreaRule_area_country_id'] : null
        );
        $taxAreaRule->setAreaCountryUuid(
            isset($data['__taxAreaRule_area_country_uuid']) ? (string)$data['__taxAreaRule_area_country_uuid'] : null
        );
        $taxAreaRule->setAreaCountryStateId(
            isset($data['__taxAreaRule_area_country_state_id']) ? (int)$data['__taxAreaRule_area_country_state_id'] : null
        );
        $taxAreaRule->setAreaCountryStateUuid(
            isset($data['__taxAreaRule_area_country_state_uuid']) ? (string)$data['__taxAreaRule_area_country_state_uuid'] : null
        );
        $taxAreaRule->setTaxId((int)$data['__taxAreaRule_tax_id']);
        $taxAreaRule->setTaxUuid((string)$data['__taxAreaRule_tax_uuid']);
        $taxAreaRule->setCustomerGroupId((int)$data['__taxAreaRule_customer_group_id']);
        $taxAreaRule->setCustomerGroupUuid((string)$data['__taxAreaRule_customer_group_uuid']);
        $taxAreaRule->setTaxRate((float)$data['__taxAreaRule_tax_rate']);
        $taxAreaRule->setName((string)$data['__taxAreaRule_name']);
        $taxAreaRule->setActive((bool)$data['__taxAreaRule_active']);

        return $taxAreaRule;
    }
}
