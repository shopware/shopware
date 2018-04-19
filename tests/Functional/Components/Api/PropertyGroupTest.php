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

use Shopware\Components\Api\Resource\PropertyGroup;
use Shopware\Components\Api\Resource\Resource;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class PropertyGroupTest extends TestCase
{
    /**
     * @var PropertyGroup
     */
    protected $resource;

    /**
     * @return PropertyGroup
     */
    public function createResource()
    {
        return new PropertyGroup();
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\CustomValidationException
     */
    public function testCreateShouldThrowCustomValidationException()
    {
        $testData = [
            'position' => 1,
            'comparable' => 1,
            'sortmode' => 2,
        ];

        $this->resource->create($testData);
    }

    public function testCreateShouldBeSuccessful()
    {
        $testData = [
            'name' => 'Eigenschaft1',
            'position' => 1,
            'comparable' => 1,
            'sortmode' => 0,
        ];

        $group = $this->resource->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Property\Group', $group);
        $this->assertGreaterThan(0, $group->getId());

        $this->assertEquals($group->getPosition(), $testData['position']);
        $this->assertEquals($group->getComparable(), $testData['comparable']);
        $this->assertEquals($group->getSortMode(), $testData['sortmode']);

        return $group->getId();
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneShouldBeSuccessful($id)
    {
        $group = $this->resource->getOne($id);
        $this->assertGreaterThan(0, $group['id']);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneShouldBeAbleToReturnObject($id)
    {
        $this->resource->setResultMode(1);
        $group = $this->resource->getOne($id);

        $this->assertInstanceOf('\Shopware\Models\Property\Group', $group);
        $this->assertGreaterThan(0, $group->getId());
    }

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

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetListShouldBeAbleToReturnObjects()
    {
        $this->resource->setResultMode(Resource::HYDRATE_OBJECT);
        $result = $this->resource->getList();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertGreaterThanOrEqual(1, $result['data']);

        $this->assertInstanceOf('\Shopware\Models\Property\Group', $result['data'][0]);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testUpdateShouldBeSuccessful($id)
    {
        $testData = [
            'name' => uniqid(rand()) . 'testProperty',
            'sortmode' => 99,
        ];

        $group = $this->resource->update($id, $testData);

        $this->assertInstanceOf('\Shopware\Models\Property\Group', $group);
        $this->assertEquals($id, $group->getId());

        $this->assertEquals($group->getName(), $testData['name']);
        $this->assertEquals($group->getSortMode(), $testData['sortmode']);

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
        $group = $this->resource->delete($id);

        $this->assertInstanceOf('\Shopware\Models\Property\Group', $group);
        $this->assertEquals(null, $group->getId());
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
}
