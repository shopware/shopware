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

namespace Shopware\Core\Framework\Struct;

/**
 * @category  Shopware\Core
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
trait ExtendableTrait
{
    /**
     * Contains an array of extension structs.
     *
     * @var Struct[]
     */
    protected $extensions = [];

    /**
     * Adds a new extension struct into the class storage.
     * The passed name is used as unique identifier and has to be stored too.
     *
     * @param string $name
     * @param Struct $extension
     */
    public function addExtension(string $name, Struct $extension): void
    {
        $this->extensions[$name] = $extension;
    }

    /**
     * @param Struct[] $extensions
     */
    public function addExtensions(array $extensions): void
    {
        foreach ($extensions as $key => $extension) {
            $this->addExtension($key, $extension);
        }
    }

    /**
     * Returns a single extension struct element of this class.
     * The passed name is used as unique identifier.
     *
     * @param $name
     *
     * @return null|Struct
     */
    public function getExtension(string $name): ?Struct
    {
        if (isset($this->extensions[$name])) {
            return $this->extensions[$name];
        }

        return null;
    }

    /**
     * Helper function which checks if an associated
     * extension exists.
     *
     * @param $name
     *
     * @return bool
     */
    public function hasExtension(string $name): bool
    {
        return isset($this->extensions[$name]);
    }

    /**
     * Returns all stored extension structures of this class.
     * The array has to be an associated array with name and extension instance.
     *
     * @return Struct[]
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function setExtensions(array $extensions): void
    {
        $this->extensions = $extensions;
    }

    public function removeExtension(string $name)
    {
        if (isset($this->extensions[$name])) {
            unset($this->extensions[$name]);
        }
    }
}
