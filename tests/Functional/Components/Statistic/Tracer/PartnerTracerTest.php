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
use Shopware\Components\Statistic\Tracer\PartnerTracer;

class PartnerTracerTest extends TestCase
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
     * @var PartnerTracer
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
        $this->connection->executeUpdate('DELETE FROM s_emarketing_partner');
        $this->connection->executeUpdate('DELETE FROM s_campaigns_mailings');

        $this->tracer = $this->container->get('shopware.statistic.tracer.partner_tracer');
        $this->context = $this->container->get('storefront.context.service')->getShopContext();
        $this->session = $this->container->get('session');
        $this->session->offsetUnset('sPartner');
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->connection->rollBack();
    }

    public function testCampaign()
    {
        $request = $this->getRequest('sCampaign1');

        $this->insertCampaign(1, 10);

        $this->tracer->traceRequest($request, $this->context);

        $this->assertEquals(
            [
                ['id' => 1, 'clicked' => 11],
            ],
            $this->getCampaigns()
        );
    }

    public function testMultipleCampaignTraces()
    {
        $request = $this->getRequest('sCampaign1');

        $this->insertCampaign(1, 10);
        $this->insertCampaign(2, 0);

        $this->tracer->traceRequest($request, $this->context);
        $this->tracer->traceRequest($request, $this->context);
        $this->tracer->traceRequest($request, $this->context);
        $this->tracer->traceRequest($request, $this->context);

        $request = $this->getRequest('sCampaign2');
        $this->tracer->traceRequest($request, $this->context);
        $this->tracer->traceRequest($request, $this->context);
        $this->tracer->traceRequest($request, $this->context);
        $this->tracer->traceRequest($request, $this->context);

        $this->assertEquals(
            [
                ['id' => 1, 'clicked' => 14],
                ['id' => 2, 'clicked' => 4],
            ],
            $this->getCampaigns()
        );
    }

    public function testPartnerCookie()
    {
        $request = $this->getRequest('p1');
        $this->insertPartner(1, 'p1', 1);
        $this->tracer->traceRequest($request, $this->context);

        $this->assertEquals('p1', $this->session->offsetGet('sPartner'));

        $cookies = $this->container->get('front')->Response()->getCookies();

        $this->assertArrayHasKey('partner', $cookies);
        $this->assertEquals('p1', $cookies['partner']['value']);
        $this->assertEquals('0', $cookies['partner']['expire']);
        $this->assertEquals('/', $cookies['partner']['path']);
    }

    public function testPartnerCookieWithoutSession()
    {
        $request = $this->getRequest('p1');
        $this->insertPartner(1, 'p1', 1);
        $this->container->reset('session');

        $this->tracer->traceRequest($request, $this->context);

        $this->assertFalse($this->session->offsetExists('sPartner'));

        $cookies = $this->container->get('front')->Response()->getCookies();
        $this->assertArrayHasKey('partner', $cookies);
        $this->assertEquals('p1', $cookies['partner']['value']);
    }

    public function testInvalidPartnerCookie()
    {
        $request = new \Enlight_Controller_Request_RequestHttp();
        $this->container->get('session');

        $_COOKIE['partner'] = 'p2';

        $this->insertPartner(1, 'p1', 1);
        $this->insertPartner(2, 'p2', 0);

        $this->tracer->traceRequest($request, $this->context);

        $this->assertFalse($this->session->offsetExists('sPartner'));
    }

    public function testPartnerCookieWithoutFrontController()
    {
        $request = $this->getRequest('p1');
        $this->container->get('session');

        $this->insertPartner(1, 'p1', 1);

        $this->container->reset('front');
        $this->tracer->traceRequest($request, $this->context);

        $this->container->load('front');
        $cookies = $this->container->get('front')->Response()->getCookies();

        $this->assertArrayNotHasKey('partner', $cookies);
    }

    public function testIndividualCookieLifeTime()
    {
        unset($_COOKIE['partner']);
        $request = $this->getRequest('p1');
        $this->insertPartner(1, 'p1', 1, 3600);
        $this->container->load('front');

        $this->tracer->traceRequest($request, $this->context);

        $this->assertEquals('p1', $this->session->offsetGet('sPartner'));

        $cookies = $this->container->get('front')->Response()->getCookies();

        $this->assertArrayHasKey('partner', $cookies);
        $this->assertEquals('p1', $cookies['partner']['value']);
        $this->assertNotEmpty($cookies['partner']['expire']);
        $this->assertEquals('/', $cookies['partner']['path']);
    }

    private function insertPartner($id, $code, $active, $lifeTime = 0)
    {
        $this->connection->insert('s_emarketing_partner', [
            'id' => $id,
            'active' => $active,
            'idcode' => $code,
            'cookielifetime' => $lifeTime,
        ]);
    }

    private function insertCampaign($id, $clicked)
    {
        $this->connection->insert('s_campaigns_mailings', [
            'id' => $id,
            'clicked' => $clicked,
        ]);
    }

    private function getCampaigns()
    {
        return $this->connection->fetchAll('SELECT id, clicked FROM s_campaigns_mailings');
    }

    private function getRequest(
        ?string $partner,
        ?string $sPartner = null
    ): \Enlight_Controller_Request_RequestHttp {
        $request = new \Enlight_Controller_Request_RequestHttp();

        if ($sPartner === null) {
            $request->setParam('partner', $partner);
        } else {
            $request->setParam('sPartner', $sPartner);
        }

        return $request;
    }
}
