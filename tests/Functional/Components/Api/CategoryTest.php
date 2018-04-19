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

namespace Shopware\Tests\Functional\Components\Api;

use Shopware\Components\Api\Resource\Category;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CategoryTest extends TestCase
{
    /**
     * @var Category
     */
    protected $resource;

    /**
     * @return Category
     */
    public function createResource()
    {
        return new Category();
    }

    public function testCreateShouldBeSuccessful()
    {
        $date = new \DateTime();
        $date->modify('-10 days');
        $added = $date->format(\DateTime::ISO8601);

        $date->modify('-3 day');
        $changed = $date->format(\DateTime::ISO8601);

        $testData = [
            'name' => 'fooobar',
            'parent' => 1,

            'position' => 3,

            'metaKeywords' => 'test, test',
            'metaDescription' => 'Description Test',
            'cmsHeadline' => 'cms headline',
            'cmsText' => 'cmsTest',

            'active' => true,
            'blog' => false,

            'external' => false,
            'hidefilter' => false,
            'hideTop' => true,

            'changed' => $changed,
            'added' => $added,

            'attribute' => [
                1 => 'test1',
                2 => 'test2',
                6 => 'test6',
            ],
        ];

        $category = $this->resource->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Category\Category', $category);
        $this->assertGreaterThan(0, $category->getId());

        $this->assertEquals($category->getActive(), $testData['active']);
        $this->assertEquals($category->getMetaDescription(), $testData['metaDescription']);
        $this->assertEquals($category->getAttribute()->getAttribute1(), $testData['attribute'][1]);
        $this->assertEquals($category->getAttribute()->getAttribute2(), $testData['attribute'][2]);
        $this->assertEquals($category->getAttribute()->getAttribute6(), $testData['attribute'][6]);

        return $category->getId();
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneShouldBeSuccessful($id)
    {
        $category = $this->resource->getOne($id);
        $this->assertGreaterThan(0, $category['id']);
    }

//    /**
//     * @depends testCreateShouldBeSuccessful
//     */
//    public function testGetOneShouldBeAbleToReturnObject($id)
//    {
//        $this->resource->setResultMode(1);
//        $category = $this->resource->getOne($id);
//
//        $this->assertInstanceOf('\Shopware\Models\Category\Category', $category);
//        $this->assertGreaterThan(0, $category->getId());
//    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetListShouldBeSuccessful()
    {
        $result = $this->resource->getList();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertGreaterThanOrEqual(1, $result['data']);
    }

//    /**
//     * @depends testCreateShouldBeSuccessful
//     */
//    public function testGetListShouldBeAbleToReturnObjects()
//    {
//        $this->resource->setResultMode(\Shopware\Components\Api\Resource\Resource::HYDRATE_OBJECT);
//        $result = $this->resource->getList();
//
//        $this->assertArrayHasKey('data', $result);
//        $this->assertArrayHasKey('total', $result);
//
//        $this->assertGreaterThanOrEqual(1, $result['total']);
//        $this->assertGreaterThanOrEqual(1, $result['data']);
//
//        $this->assertInstanceOf('\Shopware\Models\Category\Category', $result['data'][0]);
//    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testUpdateShouldBeSuccessful($id)
    {
        $testData = [
            'active' => true,
            'name' => uniqid(rand()) . 'testkategorie',
            'attribute' => [1 => 'nase'],
        ];

        $category = $this->resource->update($id, $testData);

        $this->assertInstanceOf('\Shopware\Models\Category\Category', $category);
        $this->assertEquals($id, $category->getId());

        $this->assertEquals($category->getActive(), $testData['active']);
        $this->assertEquals($category->getName(), $testData['name']);
        $this->assertEquals($category->getAttribute()->getAttribute1(), $testData['attribute'][1]);

        return $id;
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testUpdateWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->update(9999999, []);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testUpdateWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->update('', []);
    }

    /**
     * @depends testUpdateShouldBeSuccessful
     */
    public function testDeleteShouldBeSuccessful($id)
    {
        $category = $this->resource->delete($id);

        $this->assertInstanceOf('\Shopware\Models\Category\Category', $category);
        $this->assertEquals(null, $category->getId());
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testDeleteWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->delete(9999999);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testDeleteWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->delete('');
    }

    public function testfindCategoryByPath()
    {
        $parts = [
            'Deutsch',
            'Foo' . uniqid(rand()),
            'Bar' . uniqid(rand()),
        ];

        $path = implode('|', $parts);

        /** @var \Shopware\Models\Category\Category $category */
        $category = $this->resource->findCategoryByPath($path);
        $this->assertEquals(null, $category);

        $category = $this->resource->findCategoryByPath($path, true);
        $this->resource->flush();

        $this->assertEquals(array_pop($parts), $category->getName());
        $this->assertEquals(array_pop($parts), $category->getParent()->getName());
        $this->assertEquals(array_pop($parts), $category->getParent()->getParent()->getName());
        $this->assertEquals(3, $category->getParent()->getParent()->getId());

        $secondCategory = $this->resource->findCategoryByPath($path, true);
        $this->resource->flush();

        $this->assertSame($category->getId(), $secondCategory->getId());
    }
}
