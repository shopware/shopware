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

namespace Shopware\ProductManufacturer\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicStruct;

class ProductManufacturerBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): ProductManufacturerBasicStruct
    {
        $productManufacturer = new ProductManufacturerBasicStruct();

        $productManufacturer->setId((int) $data['__productManufacturer_id']);
        $productManufacturer->setUuid((string) $data['__productManufacturer_uuid']);
        $productManufacturer->setName((string) $data['__productManufacturer_name']);
        $productManufacturer->setImg((string) $data['__productManufacturer_img']);
        $productManufacturer->setLink((string) $data['__productManufacturer_link']);
        $productManufacturer->setDescription(isset($data['__productManufacturer_description']) ? (string) $data['__productManufacturer_description'] : null);
        $productManufacturer->setMetaTitle(isset($data['__productManufacturer_meta_title']) ? (string) $data['__productManufacturer_meta_title'] : null);
        $productManufacturer->setMetaDescription(isset($data['__productManufacturer_meta_description']) ? (string) $data['__productManufacturer_meta_description'] : null);
        $productManufacturer->setMetaKeywords(isset($data['__productManufacturer_meta_keywords']) ? (string) $data['__productManufacturer_meta_keywords'] : null);
        $productManufacturer->setUpdatedAt(new \DateTime($data['__productManufacturer_updated_at']));

        return $productManufacturer;
    }
}
