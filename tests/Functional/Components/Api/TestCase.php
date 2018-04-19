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

use Shopware\Components\Api\Resource\Resource as APIResource;

/**
 * Abstract TestCase for Resource-Tests
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
abstract class TestCase extends \Enlight_Components_Test_TestCase
{
    /**
     * @var APIResource
     */
    protected $resource;

    protected function setUp()
    {
        parent::setUp();

        Shopware()->Models()->clear();

        $this->resource = $this->createResource();
        $this->resource->setManager(Shopware()->Models());
    }

    protected function tearDown()
    {
        Shopware()->Models()->clear();
    }

    /**
     * @return APIResource
     */
    abstract public function createResource();

    /**
     * @expectedException \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function testGetOneWithMissingPrivilegeShouldThrowPrivilegeException()
    {
        $this->resource->setRole('dummy');
        $this->resource->setAcl($this->getAclMock());

        $this->resource->getOne(1);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testGetOneWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->getOne(9999999);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testGetOneWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->getOne('');
    }

    protected function getAclMock()
    {
        $aclMock = $this->createMock(\Shopware_Components_Acl::class);

        $aclMock->expects($this->any())
                ->method('has')
                ->willReturn(true);

        $aclMock->expects($this->any())
                ->method('isAllowed')
                ->willReturn(false);

        return $aclMock;
    }
}
