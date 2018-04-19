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

namespace Shopware\Framework\Struct;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class AttributeHydrator extends Hydrator
{
    public function hydrate(array $data): Attribute
    {
        $attribute = new Attribute();
        $translation = $this->getTranslation($data, null);
        $translation = $this->extractFields('___attribute_', $translation);
        unset($data['translation']);
        unset($data['translation_fallback']);

        foreach ($data as $key => $value) {
            if (isset($translation[$key])) {
                $attribute->set($key, $translation[$key]);
            } else {
                $attribute->set($key, $value);
            }
        }

        return $attribute;
    }

    public function addAttribute(ExtendableInterface $struct, array $data, string $arrayKey, ?string $attributeKey = null, ?string $translationKey = null): void
    {
        $arrayKey = '__' . $arrayKey . '_';
        $attribute = $this->extractFields($arrayKey, $data);

        if ($attributeKey === null) {
            $attributeKey = 'core';
        }

        if ($translationKey) {
            $translationKey = '__' . $translationKey . '_translation';
            $attribute['translation'] = !empty($data[$translationKey]) ? $data[$translationKey] : null;
            $attribute['translation_fallback'] = !empty($data[$translationKey . '_fallback']) ? $data[$translationKey . '_fallback'] : null;
        }

        $struct->addAttribute($attributeKey, $this->hydrate($attribute));
    }
}
