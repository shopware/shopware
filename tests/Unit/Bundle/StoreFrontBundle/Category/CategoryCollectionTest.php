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

namespace Shopware\Tests\Unit\Bundle\StoreFrontBundle\Category;

use PHPUnit\Framework\TestCase;
use Shopware\Category\Struct\Category;
use Shopware\Category\Struct\CategoryCollection;

class CategoryCollectionTest extends TestCase
{
    public function testGetTreeWithEmptyCategories()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([]);

        $this->assertSame([], $collection->getTree(null));
    }

    public function testGetTreeWithOneLevel()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [1], 'First level 01'),
            self::create(2, null, [1], 'First level 02'),
            self::create(3, null, [1], 'First level 03'),
        ]);
        $this->assertEquals(
            [
                self::create(1, null, [1], 'First level 01'),
                self::create(2, null, [1], 'First level 02'),
                self::create(3, null, [1], 'First level 03'),
            ],
            $collection->getTree(null)
        );
    }

    public function testGetTreeWithNoneExistingParentId()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [1], 'First level 01'),
            self::create(2, null, [1], 'First level 02'),
            self::create(3, null, [1], 'First level 03'),
        ]);

        $this->assertSame([], $collection->getTree(100));
    }

    public function testGetNestedTree()
    {
        $collection = new CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
            self::create(3, 2, [2, 1], 'Third level 01'),
            self::create(4, 1, [1], 'Second level 02'),
            self::create(5, 4, [4, 1], 'Third level 02'),
        ]);

        $this->assertEquals(
            [
                self::create(1, null, [], 'First level 01', ['children' => [
                    self::create(2, 1, [1], 'Second level 01', ['children' => [
                        self::create(3, 2, [2, 1], 'Third level 01'),
                    ]]),
                    self::create(4, 1, [1], 'Second level 02', ['children' => [
                        self::create(5, 4, [4, 1], 'Third level 02'),
                    ]]),
                ]]),
            ],
            $collection->getTree(null)
        );
    }

    public function testGetTreeRemovesElementsWithoutParent()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
            self::create(3, 2, [2, 1], 'Third level 01'),
            self::create(4, 1, [1], 'Second level 02'),
            self::create(5, 6, [6, 1], 'Third level 02'),
        ]);

        $this->assertEquals(
            [
                self::create(1, null, [], 'First level 01', ['children' => [
                    self::create(2, 1, [1], 'Second level 01', ['children' => [
                        self::create(3, 2, [2, 1], 'Third level 01'),
                    ]]),
                    self::create(4, 1, [1], 'Second level 02'),
                ]]),
            ],
            $collection->getTree(null)
        );
    }

    public function testGetNestedTreeWithSubParent()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
            self::create(3, 2, [2, 1], 'Third level 01'),

            self::create(4, 1, [1], 'Second level 02'),
            self::create(5, 4, [4, 1], 'Third level 02'),
        ]);

        $this->assertEquals(
            [
                self::create(2, 1, [1], 'Second level 01', ['children' => [
                    self::create(3, 2, [2, 1], 'Third level 01'),
                ]]),
                self::create(4, 1, [1], 'Second level 02', ['children' => [
                    self::create(5, 4, [4, 1], 'Third level 02'),
                ]]),
            ],
            $collection->getTree(1)
        );
    }

    public function testGetIds()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
            self::create(3, 2, [2, 1], 'Third level 01'),

            self::create(4, 1, [1], 'Second level 02'),
            self::create(5, 4, [4, 1], 'Third level 02'),
        ]);

        $this->assertSame(
            [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5],
            $collection->getIds()
        );
    }

    public function testGetPaths()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
            self::create(3, 2, [2, 1], 'Third level 01'),
            self::create(4, 1, [1], 'Second level 02'),
            self::create(5, 4, [4, 1], 'Third level 02'),
        ]);

        $this->assertSame(
            [
                1 => [],
                2 => [1],
                3 => [2, 1],
                4 => [1],
                5 => [4, 1],
            ],
            $collection->getPaths()
        );
    }

    public function testGetIdsWithPath()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
            self::create(3, 2, [2, 1], 'Third level 01'),
            self::create(4, 1, [1], 'Second level 02'),
            self::create(5, 50, [50, 1], 'Third level 02'),
        ]);

        $this->assertSame(
            [1, 2, 3, 4, 5, 50],
            $collection->getIdsIncludingPaths()
        );
    }

    public function testGetByKey()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
        ]);

        $this->assertEquals(
            self::create(1, null, [], 'First level 01'),
            $collection->get(1)
        );
    }

    public function testGetWithNoneExistingKey()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
        ]);

        $this->assertSame(
            null,
            $collection->get(10)
        );
    }

    public function testGetById()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
        ]);

        $this->assertEquals(
            self::create(2, 1, [1], 'Second level 01'),
            $collection->get(2)
        );
    }

    public function testGetByIdWithNoneExisting()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
            self::create(2, 1, [1], 'Second level 01'),
        ]);

        $this->assertEquals(
            null,
            $collection->get(5)
        );
    }

    public function testAddCategory()
    {
        $collection = new \Shopware\Category\Struct\CategoryCollection([
            self::create(1, null, [], 'First level 01'),
        ]);

        $collection->add(self::create(2, 1, [1], 'Second level 01'));

        $this->assertEquals(
            new \Shopware\Category\Struct\CategoryCollection([
                self::create(1, null, [], 'First level 01'),
                self::create(2, 1, [1], 'Second level 01'),
            ]),
            $collection
        );
    }

    private static function create(int $id, ?int $parentId, array $path, string $name, array $options = [])
    {
        $category = new \Shopware\Category\Struct\Category($id, $parentId, $path, $name);
        $category->assign($options);

        return $category;
    }
}
