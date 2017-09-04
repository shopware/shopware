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

namespace Shopware\AreaCountry\Reader;

use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\Framework\Struct\Hydrator;

class AreaCountryBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): AreaCountryBasicStruct
    {
        $areaCountry = new AreaCountryBasicStruct();

        $areaCountry->setId((int) $data['__areaCountry_id']);
        $areaCountry->setUuid((string) $data['__areaCountry_uuid']);
        $areaCountry->setName(isset($data['__areaCountry_name']) ? (string) $data['__areaCountry_name'] : null);
        $areaCountry->setIso(isset($data['__areaCountry_iso']) ? (string) $data['__areaCountry_iso'] : null);
        $areaCountry->setAreaId(isset($data['__areaCountry_area_id']) ? (int) $data['__areaCountry_area_id'] : null);
        $areaCountry->setEn(isset($data['__areaCountry_en']) ? (string) $data['__areaCountry_en'] : null);
        $areaCountry->setPosition(isset($data['__areaCountry_position']) ? (int) $data['__areaCountry_position'] : null);
        $areaCountry->setNotice(isset($data['__areaCountry_notice']) ? (string) $data['__areaCountry_notice'] : null);
        $areaCountry->setShippingFree(isset($data['__areaCountry_shipping_free']) ? (bool) $data['__areaCountry_shipping_free'] : null);
        $areaCountry->setTaxFree(isset($data['__areaCountry_tax_free']) ? (bool) $data['__areaCountry_tax_free'] : null);
        $areaCountry->setTaxfreeForVatId(isset($data['__areaCountry_taxfree_for_vat_id']) ? (bool) $data['__areaCountry_taxfree_for_vat_id'] : null);
        $areaCountry->setTaxfreeVatidChecked(isset($data['__areaCountry_taxfree_vatid_checked']) ? (bool) $data['__areaCountry_taxfree_vatid_checked'] : null);
        $areaCountry->setActive(isset($data['__areaCountry_active']) ? (bool) $data['__areaCountry_active'] : null);
        $areaCountry->setIso3(isset($data['__areaCountry_iso3']) ? (string) $data['__areaCountry_iso3'] : null);
        $areaCountry->setDisplayStateInRegistration((bool) $data['__areaCountry_display_state_in_registration']);
        $areaCountry->setForceStateInRegistration((bool) $data['__areaCountry_force_state_in_registration']);
        $areaCountry->setAreaUuid((string) $data['__areaCountry_area_uuid']);

        return $areaCountry;
    }
}
