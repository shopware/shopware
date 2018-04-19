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

namespace Shopware\Product\Writer\Api;

class FieldCollection
{
    /**
     * @var Field[]
     */
    private $fields;

    /**
     * @param Field[] ...$fields
     */
    public function __construct(Field ...$fields)
    {
        $this->fields = $fields;
    }

    /**
     * @return Field[]
     */
    public function getFields(string $classFilter): array
    {
        return array_values(array_filter($this->fields, function (Field $field) use ($classFilter) {
            return $field instanceof $classFilter;
        }));
    }

    public function getField(string $classFilter): Field
    {
        $fields = $this->getFields($classFilter);

        if (count($fields) !== 1) {
            throw new \RuntimeException(sprintf('Unable to find field %s', $classFilter));
        }

        return $fields[0];
    }

    /**
     * @return string[]
     */
    public function getFieldNames(string $classFilter): array
    {
        return array_map(function (Field $field) {
            return $field->getName();
        }, $this->getFields($classFilter));
    }

    public function getFieldClasses(): array
    {
        return array_map(function (Field $field) {
            return get_class($field);
        }, $this->fields);
    }
}
