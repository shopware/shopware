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
use Shopware\Components\Statistic\Tracer\VisitorTracer;

class VisitorTracerTest extends TestCase
{
    const MOBILE = 'mobile';
    const TABLET = 'tablet';
    const DESKTOP = 'desktop';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var VisitorTracer
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
        $this->connection->executeUpdate('DELETE FROM s_statistics_visitors');
        $this->connection->executeUpdate('DELETE FROM s_statistics_pool');

        $this->tracer = $this->container->get('shopware.statistic.tracer.visitor_tracer');
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->connection->rollBack();
    }

    public function testFirstVisitor()
    {
        $request = $this->getRequest('0.0.0.0', self::DESKTOP);

        $date = (new \DateTime())->format('Y-m-d');

        $this->tracer->traceRequest($request, $this->getContext(1));

        $this->assertEquals(
            [
                [
                    'shopID' => 1,
                    'pageimpressions' => 1,
                    'uniquevisits' => 1,
                    'deviceType' => self::DESKTOP,
                    'datum' => $date,
                ],
            ],
            $this->getVisitors()
        );

        $this->assertEquals(
            [
                ['remoteaddr' => '0.0.0.0', 'datum' => $date],
            ],
            $this->getPool()
        );
    }

    public function testFirstVisitorForDevice()
    {
        $date = (new \DateTime())->format('Y-m-d');

        $request = $this->getRequest('0.0.0.0', self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext(1));

        $request = $this->getRequest('0.0.0.1', self::TABLET);
        $this->tracer->traceRequest($request, $this->getContext(1));

        $this->assertEquals(
            [
                [
                    'shopID' => 1,
                    'pageimpressions' => 1,
                    'uniquevisits' => 1,
                    'deviceType' => self::DESKTOP,
                    'datum' => $date,
                ],
                [
                    'shopID' => 1,
                    'pageimpressions' => 1,
                    'uniquevisits' => 1,
                    'deviceType' => self::TABLET,
                    'datum' => $date,
                ],
            ],
            $this->getVisitors()
        );

        $this->assertEquals(
            [
                ['remoteaddr' => '0.0.0.0', 'datum' => $date],
                ['remoteaddr' => '0.0.0.1', 'datum' => $date],
            ],
            $this->getPool()
        );
    }

    public function testDifferentShopsWithDevices()
    {
        $date = (new \DateTime())->format('Y-m-d');

        $request = $this->getRequest('0.0.0.0', self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext(1));
        $this->tracer->traceRequest($request, $this->getContext(1));

        $request = $this->getRequest('0.0.0.1', self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext(1));
        $this->tracer->traceRequest($request, $this->getContext(1));

        $request = $this->getRequest('0.0.0.2', self::TABLET);
        $this->tracer->traceRequest($request, $this->getContext(1));

        $request = $this->getRequest('0.0.0.3', self::MOBILE);
        $this->tracer->traceRequest($request, $this->getContext(1));

        $request = $this->getRequest('0.0.0.0', self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext(2));

        $request = $this->getRequest('0.0.0.1', self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext(2));

        $this->assertEquals(
            [
                [
                    'shopID' => 1,
                    'pageimpressions' => 4,
                    'uniquevisits' => 2,
                    'deviceType' => self::DESKTOP,
                    'datum' => $date,
                ],
                [
                    'shopID' => 1,
                    'pageimpressions' => 1,
                    'uniquevisits' => 1,
                    'deviceType' => self::TABLET,
                    'datum' => $date,
                ],
                [
                    'shopID' => 1,
                    'pageimpressions' => 1,
                    'uniquevisits' => 1,
                    'deviceType' => self::MOBILE,
                    'datum' => $date,
                ],
                [
                    'shopID' => 2,
                    'pageimpressions' => 2,
                    'uniquevisits' => 1,
                    'deviceType' => self::DESKTOP,
                    'datum' => $date,
                ],
            ],
            $this->getVisitors()
        );

        $this->assertEquals(
            [
                ['remoteaddr' => '0.0.0.0', 'datum' => $date],
                ['remoteaddr' => '0.0.0.1', 'datum' => $date],
                ['remoteaddr' => '0.0.0.2', 'datum' => $date],
                ['remoteaddr' => '0.0.0.3', 'datum' => $date],
            ],
            $this->getPool()
        );
    }

    private function getPool()
    {
        return $this->connection->fetchAll('SELECT `remoteaddr`, `datum` FROM s_statistics_pool');
    }

    private function getVisitors()
    {
        return $this->connection->fetchAll('SELECT datum, shopID, pageimpressions, uniquevisits, deviceType FROM s_statistics_visitors');
    }

    private function getContext($shopId = 1)
    {
        $context = $this->container->get('storefront.context.service')
            ->getShopContext();

        $context->getShop()->setId($shopId);

        return $context;
    }

    private function getRequest(
        $clientIp,
        $deviceType
    ): \Enlight_Controller_Request_RequestHttp {
        $request = new \Enlight_Controller_Request_RequestHttp();

        $_SERVER['REMOTE_ADDR'] = $clientIp;
        $_COOKIE['x-ua-device'] = $deviceType;

        return $request;
    }
}
