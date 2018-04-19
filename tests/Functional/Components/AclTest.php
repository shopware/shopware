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
class Shopware_Tests_Components_AclTest extends Enlight_Components_Test_TestCase
{
    /**
     * @var \Shopware_Components_Acl
     */
    private $acl;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->acl = Shopware()->Acl();
    }

    /**
     * Test case
     */
    public function testAclShouldContainRoles()
    {
        $roles = $this->acl->getRoles();
        $this->assertGreaterThan(0, count($roles));
    }

    /**
     * Test case
     */
    public function testAclShouldContainResources()
    {
        $resources = $this->acl->getResources();
        $this->assertGreaterThan(0, count($resources));
    }

    /**
     * Test case
     *
     * @expectedException \Zend_Acl_Exception
     */
    public function testTestNotExistingRoleShouldThrowException()
    {
        $role = 'this_is_a_not_existing_role';
        $privilege = 'create';
        $resource = 'debug_test';

        $this->acl->isAllowed($role, $resource, $privilege);
    }

    /**
     * Test case
     *
     * @expectedException \Zend_Acl_Exception
     */
    public function testTestNotExistingResourceShouldThrowException()
    {
        $role = 'Test-Group1';
        $privilege = 'create';
        $resource = 'this_is_a_not_existing_resource';

        $this->acl->isAllowed($role, $resource, $privilege);
    }

    /**
     * Test case
     */
    public function testTestNotExistingPrivilegeShouldNotThrowException()
    {
        $role = 'local_admins';
        $privilege = 'this_is_a_not_existing_privilege';
        $resource = 'debug_test';

        $this->assertTrue($this->acl->isAllowed($role, $resource, $privilege));
    }

    /**
     * Test case
     */
    public function testTestLocalAdminsShouldHaveAllPrivileges()
    {
        $role = 'local_admins';
        $resource = 'debug_test';

        $this->assertTrue($this->acl->isAllowed($role, $resource, 'create'));
        $this->assertTrue($this->acl->isAllowed($role, $resource, 'read'));
        $this->assertTrue($this->acl->isAllowed($role, $resource, 'update'));
        $this->assertTrue($this->acl->isAllowed($role, $resource, 'delete'));
    }
}
