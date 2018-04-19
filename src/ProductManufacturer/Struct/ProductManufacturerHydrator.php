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

namespace Shopware\ProductManufacturer\Struct;

use Shopware\Framework\Struct\AttributeHydrator;
use Shopware\Framework\Struct\Hydrator;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ProductManufacturerHydrator extends Hydrator
{
    /**
     * @var AttributeHydrator
     */
    private $attributeHydrator;

    /**
     * @var array
     */
    private $mapping = [
        'metaTitle' => 'meta_title',
        'metaDescription' => 'meta_description',
        'metaKeywords' => 'meta_keywords',
    ];

    public function __construct(AttributeHydrator $attributeHydrator)
    {
        $this->attributeHydrator = $attributeHydrator;
    }

    public function hydrate(array $data): ProductManufacturer
    {
        $manufacturer = new ProductManufacturer();
        $this->assignData($manufacturer, $data);

        return $manufacturer;
    }

    private function assignData(ProductManufacturer $manufacturer, array $data): void
    {
        $translation = $this->getTranslation($data, '__manufacturer', $this->mapping);
        $data = array_merge($data, $translation);

        if (isset($data['__manufacturer_id'])) {
            $manufacturer->setId((int) $data['__manufacturer_id']);
        }

        if (isset($data['__manufacturer_name'])) {
            $manufacturer->setName($data['__manufacturer_name']);
        }

        if (isset($data['__manufacturer_description'])) {
            $manufacturer->setDescription($data['__manufacturer_description']);
        }

        if (isset($data['__manufacturer_meta_title'])) {
            $manufacturer->setMetaTitle($data['__manufacturer_meta_title']);
        }

        if (isset($data['__manufacturer_meta_description'])) {
            $manufacturer->setMetaDescription($data['__manufacturer_meta_description']);
        }

        if (isset($data['__manufacturer_meta_keywords'])) {
            $manufacturer->setMetaKeywords($data['__manufacturer_meta_keywords']);
        }

        if (isset($data['__manufacturer_link'])) {
            $manufacturer->setLink($data['__manufacturer_link']);
        }

        if (isset($data['__manufacturer_img'])) {
            $manufacturer->setCoverFile($data['__manufacturer_img']);
        }

        if (isset($data['__manufacturerAttribute_id'])) {
            $this->attributeHydrator->addAttribute($manufacturer, $data, 'manufacturerAttribute', null, 'manufacturer');
        }
    }
}
