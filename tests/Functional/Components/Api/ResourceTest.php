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

use Shopware\Components\Api\Resource\Resource;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ResourceTest extends \Enlight_Components_Test_TestCase
{
    /**
     * @var resource
     */
    private $resource;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        Shopware()->Models()->clear();

        $this->resource = $this->getMockForAbstractClass('\Shopware\Components\Api\Resource\Resource');

        $this->resource->setManager(Shopware()->Models());
    }

    public function testResultModeShouldDefaultToArray()
    {
        $this->assertEquals($this->resource->getResultMode(), Resource::HYDRATE_ARRAY);
    }

    public function testSetResultModeShouldShouldWork()
    {
        $this->resource->setResultMode(Resource::HYDRATE_OBJECT);

        $this->assertEquals($this->resource->getResultMode(), Resource::HYDRATE_OBJECT);
    }

    public function testAutoFlushShouldDefaultToTrue()
    {
        $this->assertEquals($this->resource->getAutoFlush(), true);
    }

    public function testSetAutoFlushShouldWork()
    {
        $this->resource->setAutoFlush(false);

        $this->assertEquals($this->resource->getAutoFlush(), false);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function testCheckPrivilegeShouldThrowException()
    {
        $aclMock = $this->createMock(\Shopware_Components_Acl::class);

        $aclMock->expects($this->any())
                ->method('has')
                ->willReturn(true);

        $aclMock->expects($this->any())
                ->method('isAllowed')
                ->willReturn(false);

        $this->resource->setRole('dummy');
        $this->resource->setAcl($aclMock);

        $this->resource->checkPrivilege('test');
    }

    public function testFooFlushShouldWork()
    {
        $aclMock = $this->createMock(\Shopware_Components_Acl::class);

        $aclMock->expects($this->any())
                ->method('isAllowed')
                ->willReturn(true);

        $this->resource->setRole('dummy');
        $this->resource->setAcl($aclMock);
        $this->assertNull($this->resource->checkPrivilege('test'));
    }
}
