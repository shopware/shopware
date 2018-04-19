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
use Shopware\Components\Statistic\Tracer\RefererTracer;

class RefererTracerTest extends TestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var \Shopware\Context\Struct\ShopContext
     */
    private $context;

    /**
     * @var RefererTracer
     */
    private $tracer;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var \Enlight_Components_Session_Namespace
     */
    private $session;

    protected function setUp()
    {
        parent::setUp();
        $this->container = Shopware()->Container();
        $this->connection = $this->container->get('dbal_connection');
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM s_statistics_referer');

        $this->context = $this->container->get('storefront.context.service')->getShopContext();
        $this->tracer = $this->container->get('shopware.statistic.tracer.referer_tracer');
        $this->session = $this->container->get('session');
        $this->session->offsetUnset('Admin');
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->connection->rollBack();
    }

    public function testWithoutReferer()
    {
        $this->tracer->traceRequest(
            $this->getRequest(null, null),
            $this->context
        );
        $this->assertEmpty($this->getAllReferer());
    }

    public function testHostReferer()
    {
        $this->tracer->traceRequest(
            $this->getRequest('http://localhost', null, 'http://localhost'),
            $this->context
        );
        $this->assertEmpty($this->getAllReferer());
    }

    public function testRefererWithoutHttp()
    {
        $this->tracer->traceRequest(
            $this->getRequest('localhost', null),
            $this->context
        );
        $this->assertEmpty($this->getAllReferer());
    }

    public function testWithoutSession()
    {
        $this->container->reset('session');

        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware.com', null, 'http://localhost'),
            $this->context
        );

        $this->assertFalse($this->container->get('session')->offsetExists('sReferer'));

        $date = (new \DateTime())->format('Y-m-d');

        $this->assertEquals(
            [
                ['datum' => $date, 'referer' => 'http://www.shopware.com'],
            ],
            $this->getAllReferer()
        );
    }

    public function testMultipleTraces()
    {
        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware1.com', null, 'http://localhost'),
            $this->context
        );
        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware2.com', null, 'http://localhost'),
            $this->context
        );
        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware3.com', null, 'http://localhost'),
            $this->context
        );

        $date = (new \DateTime())->format('Y-m-d');

        $this->assertEquals(
            [
                ['datum' => $date, 'referer' => 'http://www.shopware1.com'],
                ['datum' => $date, 'referer' => 'http://www.shopware2.com'],
                ['datum' => $date, 'referer' => 'http://www.shopware3.com'],
            ],
            $this->getAllReferer()
        );
    }

    public function testRefererWithPartner()
    {
        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware.com', 'sw', 'http://localhost'),
            $this->context
        );
        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware2.com', 'sw2', 'http://localhost'),
            $this->context
        );
        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware.com', null, 'http://localhost'),
            $this->context
        );

        $date = (new \DateTime())->format('Y-m-d');
        $this->assertEquals(
            [
                ['datum' => $date, 'referer' => 'http://www.shopware.com$sw'],
                ['datum' => $date, 'referer' => 'http://www.shopware2.com$sw2'],
                ['datum' => $date, 'referer' => 'http://www.shopware.com'],
            ],
            $this->getAllReferer()
        );
    }

    public function testAdminSession()
    {
        $this->session->offsetSet('Admin', true);

        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware.com', 'sw', 'http://localhost'),
            $this->context
        );
        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware2.com', 'sw2', 'http://localhost'),
            $this->context
        );
        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware.com', null, 'http://localhost'),
            $this->context
        );

        $this->assertEmpty($this->getAllReferer());
    }

    public function testSessionValue()
    {
        $this->tracer->traceRequest(
            $this->getRequest('http://www.shopware.com', null, 'http://localhost'),
            $this->context
        );

        $this->assertEquals(
            'http://www.shopware.com',
            $this->container->get('session')->offsetGet('sReferer')
        );

        $date = (new \DateTime())->format('Y-m-d');

        $this->assertEquals(
            [
                ['datum' => $date, 'referer' => 'http://www.shopware.com'],
            ],
            $this->getAllReferer()
        );
    }

    private function getAllReferer()
    {
        return $this->connection->fetchAll('SELECT datum, referer FROM s_statistics_referer');
    }

    private function getRequest(
        ?string $referer,
        ?string $partner,
        ?string $host = null
    ): \Enlight_Controller_Request_RequestHttp {
        $request = new \Enlight_Controller_Request_RequestHttp();
        $request->setParam('referer', $referer);
        $request->setParam('partner', $partner);

        if ($host !== null) {
            $_SERVER['HTTP_HOST'] = $host;
        }

        return $request;
    }
}
