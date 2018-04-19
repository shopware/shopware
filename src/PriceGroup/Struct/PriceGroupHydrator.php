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

namespace Shopware\PriceGroup\Struct;

use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountCollection;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountHydrator;

class PriceGroupHydrator
{
    /**
     * @var PriceGroupDiscountHydrator
     */
    private $discountHydrator;

    public function __construct(PriceGroupDiscountHydrator $discountHydrator)
    {
        $this->discountHydrator = $discountHydrator;
    }

    public function hydrate($data): PriceGroup
    {
        $group = new PriceGroup();

        $first = $data[0];

        $group->setId((int) $first['__priceGroup_id']);
        $group->setName($first['__priceGroup_description']);

        $discounts = new PriceGroupDiscountCollection();
        foreach ($data as $row) {
            $discounts->add($this->discountHydrator->hydrate($row));
        }

        $group->setDiscounts($discounts);

        return $group;
    }
}
