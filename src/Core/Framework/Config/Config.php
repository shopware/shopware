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

namespace Shopware\Framework\Config;

use Symfony\Component\HttpFoundation\ParameterBag;

class Config extends ParameterBag implements \ArrayAccess
{
    public function __isset($name)
    {
        return $this->has($name);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    public function __call($name, $args = null)
    {
        return $this->get($name);
    }

    public function setShop($shop)
    {
    }

    public function formatName($name)
    {
        if (strpos($name, 's') === 0 && preg_match('#^s[A-Z]#', $name)) {
            $name = substr($name, 1);
        }

        return str_replace('_', '', strtolower($name));
    }

    public function getByNamespace($namespace, $name, $default = null)
    {
        // TODO: Implement getByNamespace() method.
    }

    public function offsetGet($name)
    {
        return $this->get($name);
    }

    public function offsetUnset($name): void
    {
        $this->remove($name);
    }

    public function offsetExists($name): bool
    {
        return $this->has($name);
    }

    public function offsetSet($name, $value)
    {
        return $this->set($name, $value);
    }
}
