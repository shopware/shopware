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

namespace Shopware\AreaCountryState\Reader;

use Shopware\AreaCountryState\Struct\AreaCountryStateBasicStruct;
use Shopware\Framework\Struct\Hydrator;

class AreaCountryStateBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): AreaCountryStateBasicStruct
    {
        $areaCountryState = new AreaCountryStateBasicStruct();

        $areaCountryState->setId((int)$data['__areaCountryState_id']);
        $areaCountryState->setUuid((string)$data['__areaCountryState_uuid']);
        $areaCountryState->setAreaCountryId(
            isset($data['__areaCountryState_area_country_id']) ? (int)$data['__areaCountryState_area_country_id'] : null
        );
        $areaCountryState->setAreaCountryUuid(
            isset($data['__areaCountryState_area_country_uuid']) ? (string)$data['__areaCountryState_area_country_uuid'] : null
        );
        $areaCountryState->setName(
            isset($data['__areaCountryState_name']) ? (string)$data['__areaCountryState_name'] : null
        );
        $areaCountryState->setShortCode((string)$data['__areaCountryState_short_code']);
        $areaCountryState->setPosition(
            isset($data['__areaCountryState_position']) ? (int)$data['__areaCountryState_position'] : null
        );
        $areaCountryState->setActive(
            isset($data['__areaCountryState_active']) ? (bool)$data['__areaCountryState_active'] : null
        );

        return $areaCountryState;
    }
}
