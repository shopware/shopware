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

namespace Shopware\Tests\Functional\Controllers\Backend;

use Doctrine\DBAL\Connection;
use Shopware\Components\Auth\BackendAuthSubscriber;
use Shopware\Components\Password\Manager;

class BackendAuthTest extends \Enlight_Components_Test_Controller_TestCase
{
    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        $this->connection = Shopware()->Container()->get('dbal_connection');
        $this->connection->beginTransaction();

        /** @var BackendAuthSubscriber $auth */
        $auth = Shopware()->Container()->get('shopware.subscriber.auth');
        $auth->setNoAuth(false);
        $auth->setNoAcl(false);

        /** @var \Shopware_Components_Auth $comp */
        $comp = Shopware()->Container()->get('auth');
        $comp->clearIdentity();

        /** @var Manager $encoder */
        $encoder = Shopware()->Container()->get('PasswordEncoder');
        $encoderName = Shopware()->PasswordEncoder()->getDefaultPasswordEncoderName();

        $this->connection->insert('s_core_auth', [
            'roleID' => 1,
            'username' => 'unittest',
            'password' => $encoder->encodePassword('password', $encoderName),
            'encoder' => $encoderName,
            'localeID' => 2,
            'active' => 1,
            'name' => 'test',
            'email' => 'test@example.com',
        ]);

        parent::setUp();
    }

    protected function tearDown()
    {
        /** @var BackendAuthSubscriber $auth */
        $auth = Shopware()->Container()->get('shopware.subscriber.auth');
        $auth->setNoAuth(false);
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testICanDisableAuth()
    {
        $this->disableAuth();
        $response = $this->dispatch('/backend/ProductStream/list');
        $this->assertSame(200, $response->getHttpResponseCode());
        $this->assertArrayHasKey('success', json_decode($response->getBody(), true));
    }

    public function testBackendSessionCanBeInitialized()
    {
        $this->disableAuth();
        $response = $this->dispatch('/backend/ProductStream/list');
        $this->assertSame(200, $response->getHttpResponseCode());
        $this->assertTrue(Shopware()->Container()->initialized('backend_session'));
    }

    public function testLogin()
    {
        $this->Request()->setMethod('POST');
        $this->Request()->setParam('username', 'unittest');
        $this->Request()->setParam('password', 'password');

        $response = $this->dispatch('/backend/login/login');
        $this->assertSame(200, $response->getHttpResponseCode());

        $response = $this->dispatch('/backend/ProductStream/list');
        $this->assertSame(200, $response->getHttpResponseCode());
    }

    public function testOnlyBackendRequiresAuth()
    {
        /** @var BackendAuthSubscriber $auth */
        $auth = Shopware()->Container()->get('shopware.subscriber.auth');
        $auth->setNoAuth(false);

        $response = $this->dispatch('/frontend/index/index');
        $this->assertSame(200, $response->getHttpResponseCode());
    }

    private function disableAuth()
    {
        /** @var BackendAuthSubscriber $auth */
        $auth = Shopware()->Container()->get('shopware.subscriber.auth');
        $auth->setNoAuth(true);
    }
}
