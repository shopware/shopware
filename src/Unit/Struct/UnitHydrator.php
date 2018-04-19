<?php
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

namespace Shopware\Unit\Struct;

use Shopware\Framework\Struct\Hydrator;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class UnitHydrator extends Hydrator
{
    /**
     * @param array $data
     *
     * @return Unit
     */
    public function hydrate(array $data): Unit
    {
        $unit = new Unit();
        $this->assignUnitData($unit, $data);

        return $unit;
    }

    private function assignUnitData(Unit $unit, array $data)
    {
        $id = (int) $data['__unit_id'];
        $translation = $this->getTranslation($data, '__unit', [], $id);
        $data = array_merge($data, $translation);

        if (isset($data['__unit_id'])) {
            $unit->setId($id);
        }

        if (isset($data['__unit_description'])) {
            $unit->setName($data['__unit_description']);
        }

        if (isset($data['__unit_unit'])) {
            $unit->setUnit($data['__unit_unit']);
        }

        if (isset($data['__unit_pack_unit'])) {
            $unit->setPackUnit($data['__unit_pack_unit']);
        }

        if (isset($data['__unit_purchase_unit'])) {
            $unit->setPurchaseUnit((float) $data['__unit_purchase_unit']);
        }

        if (isset($data['__unit_reference_unit'])) {
            $unit->setReferenceUnit((float) $data['__unit_reference_unit']);
        }

        if (isset($data['__unit_purchase_steps'])) {
            $unit->setPurchaseStep((int) $data['__unit_purchase_steps']);
        }

        if (isset($data['__unit_min_purchase'])) {
            $unit->setMinPurchase((int) $data['__unit_min_purchase']);
        }

        if (isset($data['__unit_max_purchase'])) {
            $unit->setMaxPurchase((int) $data['__unit_max_purchase']);
        }
    }
}
