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

namespace Shopware\Tests\Functional\DataGenerator;

class CategoryDataGenerator
{
    /**
     * Saves the given categories to the database and returns an array with the ids of the inserted categories.
     *
     * @example:
     *      $categories = [
     *         [
     *             'name' => 'first level',
     *             'children' => [
     *                 ['name' => 'second level 01'],
     *                 ['name' => 'second level 02'],
     *             ]
     *         ],
     *         [
     *             'name' => 'first level 02',
     *             'children' => [
     *                 ['name' => 'second level 03'],
     *             ]
     *         ],
     *     ]
     *     $path = [1]
     *
     * @param array[] $categories
     * @param array   $path
     *
     * @return array
     */
    public function saveTree(array $categories, array $path): array
    {
        $connection = Shopware()->Container()->get('dbal_connection');

        foreach ($categories as &$category) {
            $connection->insert('s_categories', [
                'active' => array_key_exists('active', $category) ? $category['active'] : 1,
                'path' => '|' . implode('|', $path) . '|',
                'description' => $category['name'],
                'parent' => $path[count($path) - 1],
            ]);

            $category['id'] = $connection->lastInsertId('s_categories');

            if ($category['children']) {
                $newPath = array_merge($path, [$category['id']]);
                $category['children'] = $this->saveTree($category['children'], $newPath);
            }
        }

        return $categories;
    }
}
