<?php
declare(strict_types=1);
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
trait ExtendableTrait
{
    /**
     * Contains an array of attribute structs.
     *
     * @var Attribute[]
     */
    protected $attributes = [];

    /**
     * Adds a new attribute struct into the class storage.
     * The passed name is used as unique identifier and has to be stored too.
     *
     * @param string    $name
     * @param Attribute $attribute
     */
    public function addAttribute(string $name, Attribute $attribute): void
    {
        $this->attributes[$name] = $attribute;
    }

    /**
     * @param Attribute[] $attributes
     */
    public function addAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $attribute) {
            $this->addAttribute($key, $attribute);
        }
    }

    /**
     * Returns a single attribute struct element of this class.
     * The passed name is used as unique identifier.
     *
     * @param $name
     *
     * @return Attribute
     */
    public function getAttribute(string $name): Attribute
    {
        return $this->attributes[$name];
    }

    /**
     * Helper function which checks if an associated
     * attribute exists.
     *
     * @param $name
     *
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Returns all stored attribute structures of this class.
     * The array has to be an associated array with name and attribute instance.
     *
     * @return Attribute[]
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
