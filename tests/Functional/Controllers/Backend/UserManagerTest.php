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
class Shopware_Tests_Controllers_Backend_UserManagerTest extends Enlight_Components_Test_Controller_TestCase
{
    protected $temporaryUsername;
    protected $temporaryUserData = [
      'username' => '',
      'password' => 'test',
      'localeId' => 1,
      'roleId' => 1,
      'name' => 'PHPUnit Testuser',
      'email' => 'test@example.com',
      'active' => 1,
    ];
    protected $temporaryRoleName;

    public function setUp()
    {
        parent::setUp();

        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAcl();
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAuth();
    }

    /**
     * Test loading of backend users
     */
    public function testUserList()
    {
        $this->dispatch('backend/UserManager/getUsers');
        $this->assertTrue($this->View()->success);
        $this->assertGreaterThan(0, count($this->View()->data));
        $this->assertEquals($this->View()->total, count($this->View()->data));
    }

    /**
     * Test load details for a random user
     */
    public function testUserDetails()
    {
        $getRandomUserId = Shopware()->Db()->fetchOne('
        SELECT id FROM s_core_auth
        ');

        $this->Request()->setParam('id', $getRandomUserId);
        $this->dispatch('backend/UserManager/getUserDetails');

        // Check if request was successful
        $this->assertTrue($this->View()->success);
        $this->assertEquals($this->View()->total, 1);

        // Check that returning data is an array
        $this->assertTrue(is_array($this->View()->data));

        // Check that data matches the requested one
        $this->assertEquals($this->View()->data['id'], $getRandomUserId);

        // Check that result does not contain passwords
        $this->assertNull($this->View()->data['password']);
    }

    /**
     * Test user creation
     *
     * @return mixed
     */
    public function testUserAdd()
    {
        $this->temporaryUsername = md5(uniqid(rand()));

        $this->Request()->setParams($this->temporaryUserData);
        $this->Request()->setParam('username', $this->temporaryUsername);

        $this->dispatch('backend/UserManager/updateUser');

        $this->assertTrue($this->View()->success);
        $this->assertEquals($this->View()->data['username'], $this->temporaryUsername);

        return $this->temporaryUsername;
    }

    /**
     * Test edit of users
     *
     * @depends testUserAdd
     */
    public function testUserEdit($username)
    {
        $this->assertTrue(true);
        $getRandomUserId = Shopware()->Db()->fetchOne('
        SELECT id FROM s_core_auth WHERE username = ?
        ', $username);

        $this->assertGreaterThan(0, $getRandomUserId);
        $this->Request()->setParam('id', $getRandomUserId);
        $this->Request()->setParam('name', 'Random');
        $this->dispatch('backend/UserManager/updateUser');
        $this->assertTrue($this->View()->success);
        $this->assertEquals('Random', Shopware()->Db()->fetchOne('SELECT name FROM s_core_auth WHERE id = ?', [$getRandomUserId]));

        return $username;
    }

    /**
     * Test deleting of users
     *
     * @depends testUserEdit
     */
    public function testUserDelete($username)
    {
        $getRandomUserId = Shopware()->Db()->fetchOne('
       SELECT id FROM s_core_auth WHERE username = ?
       ', $username);

        $this->Request()->setParam('id', $getRandomUserId);
        $this->dispatch('backend/UserManager/deleteUser');
        $this->assertTrue($this->View()->success, 'User ' . $this->temporaryUsername . ' with id ' . $getRandomUserId . ' not found');
    }

    /**
     * Test that roles could read from model
     */
    public function testListRoles()
    {
        $this->dispatch('backend/UserManager/getRoles');

        $this->assertTrue($this->View()->success);
        $this->assertGreaterThan(0, count($this->View()->data));
        $this->assertEquals($this->View()->total, count($this->View()->data));
    }

    /**
     * Test creating of roles
     *
     * @return string
     */
    public function testCreateRole()
    {
        $randomRoleName = md5(uniqid(rand()));
        $this->Request()->setParam('parentID', null);
        $this->Request()->setParam('name', $randomRoleName);
        $this->Request()->setParam('description', 'Test');
        $this->Request()->setParam('source', 'Test');
        $this->Request()->setParam('enabled', 1);
        $this->Request()->setParam('admin', 1);
        $this->dispatch('backend/UserManager/updateRole');
        $this->assertTrue($this->View()->success);

        return $randomRoleName;
    }

    /**
     * Test editing of roles
     *
     * @depends testCreateRole
     */
    public function testEditRole($randomRoleName)
    {
        $getRandomRoleId = Shopware()->Db()->fetchOne('
        SELECT id FROM s_core_auth_roles WHERE name = ?
        ', $randomRoleName);
        $this->assertGreaterThan(0, $getRandomRoleId);

        $this->Request()->setParam('id', $getRandomRoleId);
        $this->Request()->setParam('enabled', false);
        $this->dispatch('backend/UserManager/updateRole');
        $this->assertTrue($this->View()->success);

        return $getRandomRoleId;
    }

    /**
     * Test deleting of roles
     *
     * @depends testEditRole
     */
    public function testDeleteRole($randomRoleId)
    {
        $this->Request()->setParam('id', $randomRoleId);
        $this->dispatch('backend/UserManager/deleteRole');

        $this->assertTrue($this->View()->success);
    }
}
