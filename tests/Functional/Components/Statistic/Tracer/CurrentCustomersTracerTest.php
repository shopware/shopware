<?php
declare(strict_types=1);
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

namespace Shopware\Tests\Functional\Components\Statistic\Tracer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Components\Statistic\Tracer\CurrentCustomersTracer;

class CurrentCustomersTracerTest extends TestCase
{
    const MOBILE = 'mobile';
    const TABLET = 'tablet';
    const DESKTOP = 'desktop';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var \Shopware\Context\Struct\ShopContext
     */
    private $context;

    /**
     * @var CurrentCustomersTracer
     */
    private $tracer;

    /**
     * @var Container
     */
    private $container;

    protected function setUp()
    {
        parent::setUp();
        $this->container = Shopware()->Container();
        $this->connection = $this->container->get('dbal_connection');
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM s_statistics_currentusers');
        $this->tracer = $this->container->get('shopware.statistic.tracer.current_customers_tracer');
        $this->context = $this->container->get('storefront.context.service')->getShopContext();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->connection->rollBack();
    }

    public function testWithNoneInitializedSession()
    {
        $request = $this->getRequest('0.0.0.0', self::DESKTOP, '/examples');
        $this->container->reset('session');

        $this->tracer->traceRequest($request, $this->context);

        $this->assertEquals(
            [
                [
                    'remoteaddr' => '0.0.0.0',
                    'page' => '/examples',
                    'userID' => '0',
                    'deviceType' => self::DESKTOP,
                ],
            ],
            $this->getCurrentCustomers()
        );
    }

    public function testUnknownCustomer()
    {
        $request = $this->getRequest('0.0.0.0', self::DESKTOP, '/examples');
        $this->prepareSession(['sUserId' => null]);

        $this->tracer->traceRequest($request, $this->context);

        $this->assertEquals(
            [
                [
                    'remoteaddr' => '0.0.0.0',
                    'page' => '/examples',
                    'userID' => '0',
                    'deviceType' => self::DESKTOP,
                ],
            ],
            $this->getCurrentCustomers()
        );
    }

    public function testLoggedInCustomer()
    {
        $request = $this->getRequest('0.0.0.0', self::DESKTOP, '/examples');
        $this->prepareSession(['sUserId' => 10]);

        $this->tracer->traceRequest($request, $this->context);

        $this->assertEquals(
            [
                [
                    'remoteaddr' => '0.0.0.0',
                    'page' => '/examples',
                    'userID' => '10',
                    'deviceType' => self::DESKTOP,
                ],
            ],
            $this->getCurrentCustomers()
        );
    }

    public function testMultipleTraces()
    {
        $request = $this->getRequest('0.0.0.0', self::DESKTOP, '/examples');
        $this->prepareSession(['sUserId' => 10]);

        $this->tracer->traceRequest($request, $this->context);

        $request = $this->getRequest('0.0.0.1', self::TABLET, '/beispiele');
        $this->prepareSession(['sUserId' => 5]);

        $this->tracer->traceRequest($request, $this->context);

        $this->assertEquals(
            [
                [
                    'remoteaddr' => '0.0.0.0',
                    'page' => '/examples',
                    'userID' => '10',
                    'deviceType' => self::DESKTOP,
                ],
                [
                    'remoteaddr' => '0.0.0.1',
                    'page' => '/beispiele',
                    'userID' => '5',
                    'deviceType' => self::TABLET,
                ],
            ],
            $this->getCurrentCustomers()
        );
    }

    public function testRequestUriFallback()
    {
        $request = $this->getRequest('0.0.0.0', self::DESKTOP, '/examples');
        $this->prepareSession(['sUserId' => 10]);

        $this->tracer->traceRequest($request, $this->context);

        $request = $this->getRequest('0.0.0.1', self::TABLET, null, 'localhost/example');
        $this->prepareSession(['sUserId' => 5]);

        $this->tracer->traceRequest($request, $this->context);

        $this->assertEquals(
            [
                [
                    'remoteaddr' => '0.0.0.0',
                    'page' => '/examples',
                    'userID' => '10',
                    'deviceType' => self::DESKTOP,
                ],
                [
                    'remoteaddr' => '0.0.0.1',
                    'page' => 'localhost/example',
                    'userID' => '5',
                    'deviceType' => self::TABLET,
                ],
            ],
            $this->getCurrentCustomers()
        );
    }

    private function getCurrentCustomers()
    {
        return $this->connection->fetchAll(
            'SELECT remoteaddr, page, userID, deviceType FROM s_statistics_currentusers'
        );
    }

    private function getRequest(
        $clientIp,
        $deviceType,
        $requestPage = '',
        $uri = ''
    ): \Enlight_Controller_Request_RequestHttp {
        $request = new \Enlight_Controller_Request_RequestHttp();

        $request->setRequestUri($uri);
        $request->setParam('requestPage', $requestPage);
        $_SERVER['REMOTE_ADDR'] = $clientIp;
        $_COOKIE['x-ua-device'] = $deviceType;

        return $request;
    }

    private function prepareSession(array $values = []): void
    {
        /** @var \Enlight_Components_Session_Namespace $session */
        $session = $this->container->get('session');

        foreach ($values as $key => $value) {
            $session->offsetSet($key, $value);
        }
    }
}
