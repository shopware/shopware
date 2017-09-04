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

namespace Shopware\Area\Reader;

use Shopware\Area\Struct\AreaBasicStruct;
use Shopware\Framework\Struct\Hydrator;

class AreaBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): AreaBasicStruct
    {
        $area = new AreaBasicStruct();

        $area->setId((int) $data['__area_id']);
        $area->setUuid((string) $data['__area_uuid']);
        $area->setName(isset($data['__area_name']) ? (string) $data['__area_name'] : null);
        $area->setActive(isset($data['__area_active']) ? (bool) $data['__area_active'] : null);

        return $area;
    }
}
