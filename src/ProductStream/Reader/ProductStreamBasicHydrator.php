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

namespace Shopware\ProductStream\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\ListingSorting\Reader\ListingSortingBasicHydrator;
use Shopware\ProductStream\Struct\ProductStreamBasicStruct;

class ProductStreamBasicHydrator extends Hydrator
{
    /**
     * @var ListingSortingBasicHydrator
     */
    private $listingSortingBasicHydrator;

    public function __construct(ListingSortingBasicHydrator $listingSortingBasicHydrator)
    {
        $this->listingSortingBasicHydrator = $listingSortingBasicHydrator;
    }

    public function hydrate(array $data): ProductStreamBasicStruct
    {
        $productStream = new ProductStreamBasicStruct();

        $productStream->setId((int) $data['__productStream_id']);
        $productStream->setUuid((string) $data['__productStream_uuid']);
        $productStream->setName((string) $data['__productStream_name']);
        $productStream->setConditions(isset($data['__productStream_conditions']) ? (string) $data['__productStream_conditions'] : null);
        $productStream->setType(isset($data['__productStream_type']) ? (int) $data['__productStream_type'] : null);
        $productStream->setDescription(isset($data['__productStream_description']) ? (string) $data['__productStream_description'] : null);
        $productStream->setListingSortingId(isset($data['__productStream_listing_sorting_id']) ? (int) $data['__productStream_listing_sorting_id'] : null);
        $productStream->setListingSortingUuid(isset($data['__productStream_listing_sorting_uuid']) ? (string) $data['__productStream_listing_sorting_uuid'] : null);
        $productStream->setListingSorting($this->listingSortingBasicHydrator->hydrate($data));

        return $productStream;
    }
}
