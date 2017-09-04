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

namespace Shopware\ListingSorting\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\ListingSorting\Struct\ListingSortingBasicStruct;

class ListingSortingBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): ListingSortingBasicStruct
    {
        $listingSorting = new ListingSortingBasicStruct();

        $listingSorting->setId((int) $data['__listingSorting_id']);
        $listingSorting->setUuid((string) $data['__listingSorting_uuid']);
        $listingSorting->setLabel((string) $data['__listingSorting_label']);
        $listingSorting->setActive((bool) $data['__listingSorting_active']);
        $listingSorting->setDisplayInCategories((bool) $data['__listingSorting_display_in_categories']);
        $listingSorting->setPosition((int) $data['__listingSorting_position']);
        $listingSorting->setPayload((string) $data['__listingSorting_payload']);

        return $listingSorting;
    }
}
