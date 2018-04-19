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

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Models_Category_PathByIdTest extends Enlight_Components_Test_TestCase
{
    /**
     * @var \Shopware\Models\Category\Repository
     */
    protected $repo = null;

    public function simpleNameArrayProvider()
    {
        return [
            [1, [1 => 'Root']],
            [3, [3 => 'Deutsch']],
            [39, [39 => 'English']],
            [6, [3 => 'Deutsch', 6 => 'Sommerwelten']],
            [11, [3 => 'Deutsch', 5 => 'Genusswelten', 11 => 'Tees und Zubehör']],
            [48, [39 => 'English', 43 => 'Worlds of indulgence', 47 => 'Teas and Accessories', 48 => 'Teas']],
        ];
    }

    public function simpleIdArrayProvider()
    {
        return [
            [1, [1 => 1]],
            [3, [3 => 3]],
            [39, [39 => 39]],
            [6, [3 => 3, 6 => 6]],
            [11, [3 => 3, 5 => 5, 11 => 11]],
            [48, [39 => 39, 43 => 43, 47 => 47, 48 => 48]],
        ];
    }

    public function multiArrayProvider()
    {
        return [
            [1, [
                1 => ['id' => 1, 'name' => 'Root', 'blog' => false],
            ]],
            [3, [
                3 => ['id' => 3, 'name' => 'Deutsch', 'blog' => false],
            ]],
            [39, [
                39 => ['id' => 39, 'name' => 'English', 'blog' => false],
            ]],
            [5, [
                3 => ['id' => 3, 'name' => 'Deutsch', 'blog' => false],
                5 => ['id' => 5, 'name' => 'Genusswelten', 'blog' => false],
            ]],
            [48, [
                39 => ['id' => 39, 'name' => 'English', 'blog' => false],
                43 => ['id' => 43, 'name' => 'Worlds of indulgence', 'blog' => false],
                47 => ['id' => 47, 'name' => 'Teas and Accessories', 'blog' => false],
                48 => ['id' => 48, 'name' => 'Teas', 'blog' => false],
            ]],
        ];
    }

    public function stringPathProvider()
    {
        return [
            [1, 'Root'],
            [3, 'Deutsch'],
            [39, 'English'],
            [5, 'Deutsch > Genusswelten'],
            [12, 'Deutsch > Genusswelten > Tees und Zubehör > Tees'],
            [48, 'English > Worlds of indulgence > Teas and Accessories > Teas'],
        ];
    }

    /**
     * @dataProvider simpleNameArrayProvider
     */
    public function testGetPathByIdWithDefaultParameters($categoryId, $expectedResult)
    {
        $result = $this->getRepo()->getPathById($categoryId);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider simpleNameArrayProvider
     */
    public function testGetPathByIdWithDefaultNameParameter($categoryId, $expectedResult)
    {
        $result = $this->getRepo()->getPathById($categoryId, 'name');
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider simpleIdArrayProvider
     */
    public function testGetPathByIdWithIdParameter($categoryId, $expectedResult)
    {
        $result = $this->getRepo()->getPathById($categoryId, 'id');
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider multiArrayProvider
     */
    public function testGetPathByIdShouldReturnArray($categoryId, $expectedResult)
    {
        $result = $this->getRepo()->getPathById($categoryId, ['id', 'name', 'blog']);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider stringPathProvider
     */
    public function testGetPathByIdShouldReturnPathAsString($categoryId, $expectedResult)
    {
        $result = $this->getRepo()->getPathById($categoryId, 'name', ' > ');
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider stringPathProvider
     */
    public function testGetPathByIdShouldReturnPathAsStringWithCustomSeparator($categoryId, $expectedResult)
    {
        $expectedResult = str_replace(' > ', '|', $expectedResult);

        $result = $this->getRepo()->getPathById($categoryId, 'name', '|');
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return Shopware\Models\Category\Repository
     */
    protected function getRepo()
    {
        if ($this->repo === null) {
            $this->repo = Shopware()->Models()->getRepository(\Shopware\Models\Category\Category::class);
        }

        return $this->repo;
    }
}
