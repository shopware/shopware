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

class ProductImpressionTracerTest extends TestCase
{
    const MOBILE = 'mobile';
    const TABLET = 'tablet';
    const DESKTOP = 'desktop';

    /**
     * @var Connection
     */
    private $connection;

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
        $this->connection->executeUpdate('DELETE FROM s_statistics_article_impression');

        $this->tracer = $this->container->get('shopware.statistic.tracer.product_impression_tracer');
        $this->session = $this->container->get('session');
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->connection->rollBack();
    }

    public function testDoNotTraceWithMissingArticleId()
    {
        $request = new \Enlight_Controller_Request_RequestHttp();

        $this->tracer->traceRequest($request, $this->getContext());

        $this->assertEmpty($this->getImpressions());
    }

    public function testCreateImpression()
    {
        $request = $this->getRequest(1, self::DESKTOP);

        $this->tracer->traceRequest($request, $this->getContext());

        $date = (new \DateTime())->format('Y-m-d');

        $this->assertEquals(
            [
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::DESKTOP,
                    'impressions' => 1,
                ],
            ],
            $this->getImpressions()
        );
    }

    public function testUpdateImpression()
    {
        $request = $this->getRequest(1, self::DESKTOP);

        $this->tracer->traceRequest($request, $this->getContext());
        $this->tracer->traceRequest($request, $this->getContext());
        $this->tracer->traceRequest($request, $this->getContext());

        $date = (new \DateTime())->format('Y-m-d');

        $this->assertEquals(
            [
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::DESKTOP,
                    'impressions' => 3,
                ],
            ],
            $this->getImpressions()
        );
    }

    public function testDifferentProducts()
    {
        $request = $this->getRequest(1, self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext());

        $request = $this->getRequest(2, self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext());

        $request = $this->getRequest(3, self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext());

        $date = (new \DateTime())->format('Y-m-d');

        $this->assertEquals(
            [
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::DESKTOP,
                    'impressions' => 1,
                ],
                [
                    'articleId' => 2,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::DESKTOP,
                    'impressions' => 1,
                ],
                [
                    'articleId' => 3,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::DESKTOP,
                    'impressions' => 1,
                ],
            ],
            $this->getImpressions()
        );
    }

    public function testDifferentShops()
    {
        $request = $this->getRequest(1, self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext(1));

        $request = $this->getRequest(1, self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext(2));

        $request = $this->getRequest(1, self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext(1));

        $date = (new \DateTime())->format('Y-m-d');

        $this->assertEquals(
            [
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::DESKTOP,
                    'impressions' => 2,
                ],
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 2,
                    'deviceType' => self::DESKTOP,
                    'impressions' => 1,
                ],
            ],
            $this->getImpressions()
        );
    }

    public function testDifferentDevices()
    {
        $request = $this->getRequest(1, self::DESKTOP);
        $this->tracer->traceRequest($request, $this->getContext(1));

        $request = $this->getRequest(1, self::TABLET);
        $this->tracer->traceRequest($request, $this->getContext(2));
        $this->tracer->traceRequest($request, $this->getContext(2));

        $request = $this->getRequest(1, self::MOBILE);
        $this->tracer->traceRequest($request, $this->getContext(1));
        $this->tracer->traceRequest($request, $this->getContext(1));

        $request = $this->getRequest(1, self::TABLET);
        $this->tracer->traceRequest($request, $this->getContext(1));
        $this->tracer->traceRequest($request, $this->getContext(1));

        $date = (new \DateTime())->format('Y-m-d');

        $this->assertEquals(
            [
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::DESKTOP,
                    'impressions' => 1,
                ],
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 2,
                    'deviceType' => self::TABLET,
                    'impressions' => 2,
                ],
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::MOBILE,
                    'impressions' => 2,
                ],
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::TABLET,
                    'impressions' => 2,
                ],
            ],
            $this->getImpressions()
        );
    }

    public function testWithInvalidDeviceType()
    {
        $request = $this->getRequest(1, 'NOT VALID');
        $this->tracer->traceRequest($request, $this->getContext(1));

        $date = (new \DateTime())->format('Y-m-d');

        $this->assertEquals(
            [
                [
                    'articleId' => 1,
                    'date' => $date,
                    'shopId' => 1,
                    'deviceType' => self::DESKTOP,
                    'impressions' => 1,
                ],
            ],
            $this->getImpressions()
        );
    }

    private function getImpressions()
    {
        return $this->connection->fetchAll(
            'SELECT articleId, `date`, shopId, deviceType, impressions FROM s_statistics_article_impression'
        );
    }

    private function getContext($shopId = 1)
    {
        $context = $this->container->get('storefront.context.service')
            ->getShopContext();

        $context->getShop()->setId($shopId);

        return $context;
    }

    private function getRequest(
        int $productId,
        ?string $deviceType
    ): \Enlight_Controller_Request_RequestHttp {
        $request = new \Enlight_Controller_Request_RequestHttp();
        $request->setParam('articleId', $productId);
        $_COOKIE['x-ua-device'] = $deviceType;

        return $request;
    }
}
