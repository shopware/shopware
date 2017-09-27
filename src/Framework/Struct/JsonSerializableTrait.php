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

trait JsonSerializableTrait
{
    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);
        $data['_class'] = get_class($this);

        return $data;
    }

    public function jsonSerializeApi(): array
    {
        $data = json_decode(json_encode($this), true);

        $vars = get_object_vars($this);
        foreach ($vars as $property => $value) {
            $data[$property] = $this->serializeJsonApi($value);
        }

        return $data;
    }

    private function serializeJsonApi($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'serializeJsonApi'], $data);
        }

        if ($data instanceof Struct) {
            return $data->jsonSerializeApi();
        }

        if ($data instanceof \IteratorAggregate) {
            $items = [];
            foreach ($data as $item) {
                $items[] = $this->serializeJsonApi($item);
            }

            return $items;
        }

        return json_decode(json_encode($data), true);
    }
}
