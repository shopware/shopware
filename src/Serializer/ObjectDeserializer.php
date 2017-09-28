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

namespace Shopware\Serializer;

use Shopware\Framework\Struct\Struct;

class ObjectDeserializer
{
    /**
     * Internal cache property which contains created reflection classes
     *
     * @var \ReflectionClass[]
     */
    private $classes = [];

    public function deserialize($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        if ($this->isDateTime($data)) {
            return new \DateTime($data['date']);
        }

        if (is_array($data) && !$this->isObject($data)) {
            return array_map([$this, 'deserialize'], $data);
        }

        //provided array is has no object class
        if (!$this->isObject($data)) {
            return $data;
        }

        $class = $data['_class'];
        unset($data['_class']);

        //iterate arguments to resolve other serialized objects
        $arguments = array_map(function ($argument) {
            return $this->deserialize($argument);
        }, $data);

        //create object instance
        return $this->createInstance($class, $arguments);
    }

    /**
     * @param mixed $argument
     *
     * @return bool
     */
    private function isObject($argument): bool
    {
        if (!is_array($argument)) {
            return false;
        }

        return array_key_exists('_class', $argument);
    }

    /**
     * @param string $class
     * @param array  $arguments
     *
     * @return object
     */
    private function createInstance(string $class, array $arguments)
    {
        $reflectionClass = $this->getReflectionClass($class);

        if (!$reflectionClass->getConstructor()) {
            $instance = $reflectionClass->newInstance();

            /* @var Struct $instance */
            $instance->assign($arguments);

            return $instance;
        }

        $constructorParams = $reflectionClass->getConstructor()->getParameters();
        $params = [];

        foreach ($constructorParams as $constructorParam) {
            $name = $constructorParam->getName();

            if (!array_key_exists($name, $arguments)) {
                if (!$constructorParam->isOptional()) {
                    throw new \RuntimeException(sprintf("Required constructor Parameter Missing: '$%s'.", $name));
                }

                $params[] = $constructorParam->getDefaultValue();

                continue;
            }

            $params[] = $arguments[$name];

            unset($arguments[$name]);
        }

        $instance = $reflectionClass->newInstanceArgs($params);

        /* @var Struct $instance */
        $instance->assign($arguments);

        return $instance;
    }

    private function getReflectionClass(string $class): \ReflectionClass
    {
        if (isset($this->classes[$class])) {
            return $this->classes[$class];
        }

        return $this->classes[$class] = new \ReflectionClass($class);
    }

    private function isDateTime($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        $keys = array_keys($data);

        return $keys == ['date', 'timezone_type', 'timezone'];
    }
}
