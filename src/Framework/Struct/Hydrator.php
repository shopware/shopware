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
class Hydrator
{
    /**
     * @param string $prefix
     * @param array  $data
     *
     * @return array
     */
    public function extractFields($prefix, $data)
    {
        $result = [];
        $len = strlen($prefix);

        foreach ($data as $field => $value) {
            if (strpos($field, $prefix) === 0) {
                $key = substr($field, $len);
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param string $prefix
     * @param array  $data
     *
     * @return array
     */
    protected function getFields($prefix, $data)
    {
        $result = [];
        foreach ($data as $field => $value) {
            if (strpos($field, $prefix) === 0) {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    /**
     * @param string $prefix
     * @param array  $data
     *
     * @return array
     */
    protected function addArrayPrefix($prefix, array $data)
    {
        $result = [];
        foreach ($data as $key => $value) {
            $key = $prefix . '_' . $key;
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param array $data
     * @param array $keys
     *
     * @return array
     */
    protected function convertArrayKeys($data, $keys)
    {
        foreach ($keys as $old => $new) {
            if (!array_key_exists($old, $data)) {
                continue;
            }

            $data[$new] = $data[$old];
            unset($data[$old]);
        }

        return $data;
    }

    /**
     * @param array  $data
     * @param string $prefix
     * @param array  $mapping
     * @param null   $id        used for `merged` translations
     * @param bool   $addPrefix
     *
     * @return array
     */
    protected function getTranslation(array $data, $prefix, array $mapping = [], $id = null, $addPrefix = true)
    {
        if ($prefix === null) {
            $key = 'translation';
        } else {
            $key = $prefix . '_translation';
        }

        $fallback = $key . '_fallback';

        $fallback = $this->extractTranslation($data, $fallback, $id);
        $translation = $this->extractTranslation($data, $key, $id);

        $translation = $translation + $fallback;

        if (!empty($mapping)) {
            $translation = $this->convertArrayKeys($translation, $mapping);
        }

        if (!$addPrefix) {
            return $translation;
        }

        return $this->addArrayPrefix($prefix, $translation);
    }

    /**
     * @param mixed  $value
     * @param string $type
     *
     * @return mixed
     */
    protected function cast($value, string $type)
    {
        if ($value === null) {
            return $value;
        }
        settype($value, $type);

        return $value;
    }

    /**
     * @param array    $data
     * @param string   $key
     * @param null|int $id
     *
     * @return array
     */
    private function extractTranslation(array $data, $key, $id = null)
    {
        if (!isset($data[$key]) || empty($data[$key])) {
            return [];
        }

        $translation = unserialize($data[$key]);
        if (!$translation) {
            return [];
        }

        if ($id === null) {
            return $translation;
        }

        if (!isset($translation[$id])) {
            return [];
        }

        return $translation[$id];
    }
}
