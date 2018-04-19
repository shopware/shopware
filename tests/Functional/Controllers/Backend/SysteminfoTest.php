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
class Shopware_Tests_Controllers_Backend_SysteminfoTest extends Enlight_Components_Test_Controller_TestCase
{
    /**
     * Standard set up for every test - just disable auth
     */
    public function setUp()
    {
        parent::setUp();

        // disable auth and acl
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAuth();
        Shopware()->Container()->get('shopware.subscriber.auth')->setNoAcl();
    }

    public function testGetConfigList()
    {
        $response = $this->dispatch('backend/systeminfo/getConfigList');

        $this->assertTrue($this->View()->success);

        $body = $response->getBody();
        $jsonBody = Zend_Json::decode($body);

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertArrayHasKey('name', $jsonBody['data'][0]);
        $this->assertArrayHasKey('group', $jsonBody['data'][0]);
        $this->assertArrayHasKey('required', $jsonBody['data'][0]);
        $this->assertArrayHasKey('version', $jsonBody['data'][0]);
        $this->assertArrayHasKey('status', $jsonBody['data'][0]);
    }

    public function testGetPathList()
    {
        $response = $this->dispatch('backend/systeminfo/getPathList');

        $this->assertTrue($this->View()->success);

        $body = $response->getBody();
        $jsonBody = Zend_Json::decode($body);

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertArrayHasKey('name', $jsonBody['data'][0]);
        $this->assertArrayHasKey('version', $jsonBody['data'][0]);
        $this->assertArrayHasKey('result', $jsonBody['data'][0]);
    }

    public function testGetFileList()
    {
        $response = $this->dispatch('backend/systeminfo/getFileList');

        $this->assertTrue($this->View()->success);

        $body = $response->getBody();
        $jsonBody = Zend_Json::decode($body);

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
    }

    public function testGetVersionList()
    {
        $response = $this->dispatch('backend/systeminfo/getVersionList');

        $this->assertTrue($this->View()->success);

        $body = $response->getBody();
        $jsonBody = Zend_Json::decode($body);

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
        $this->assertArrayHasKey('name', $jsonBody['data'][0]);
        $this->assertArrayHasKey('version', $jsonBody['data'][0]);
    }

    public function testGetEncoder()
    {
        $response = $this->dispatch('backend/systeminfo/getEncoder');

        $this->assertTrue($this->View()->success);

        $body = $response->getBody();
        $jsonBody = Zend_Json::decode($body);

        $this->assertArrayHasKey('data', $jsonBody);
        $this->assertArrayHasKey('success', $jsonBody);
    }
}
