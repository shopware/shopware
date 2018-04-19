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
use Shopware\Components\Statistic\Tracer\CartTracer;

class CartTracerTest extends TestCase
{
    const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36';
    const SESSION_ID = 'adb8k3vn6n0qmu7bpi9q1ci47q';
    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        parent::setUp();
        $this->connection = Shopware()->Container()->get('dbal_connection');
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM s_order_basket');
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->connection->rollBack();
    }

    public function testUnknownController()
    {
        $request = $this->getRequest(null, self::USER_AGENT);
        $this->prepareSession(self::SESSION_ID);

        /** @var CartTracer $tracer */
        $tracer = Shopware()->Container()->get('shopware.statistic.tracer.cart_tracer');
        $context = Shopware()->Container()->get('storefront.context.service')->getShopContext();

        $this->insertCart(self::SESSION_ID, '', '', 1);

        $tracer->traceRequest($request, $context);

        $this->assertEquals(
            [
                'sessionID' => self::SESSION_ID,
                'useragent' => '',
                'userID' => '1',
                'lastviewport' => '',
            ],
            $this->getCart(self::SESSION_ID)
        );
    }

    public function testNoneInitializedSession()
    {
        $request = $this->getRequest('checkout', self::USER_AGENT);
        Shopware()->Container()->reset('session');

        /** @var CartTracer $tracer */
        $tracer = Shopware()->Container()->get('shopware.statistic.tracer.cart_tracer');
        $context = Shopware()->Container()->get('storefront.context.service')->getShopContext();

        $this->insertCart(self::SESSION_ID, '', '', 1);
        $tracer->traceRequest($request, $context);

        $this->assertEquals(
            [
                'sessionID' => self::SESSION_ID,
                'useragent' => '',
                'userID' => '1',
                'lastviewport' => '',
            ],
            $this->getCart(self::SESSION_ID)
        );
    }

    public function testNoneExistingUserAgent()
    {
        $request = $this->getRequest('checkout', '');
        $this->prepareSession(self::SESSION_ID, ['sUserId' => 1]);

        /** @var CartTracer $tracer */
        $tracer = Shopware()->Container()->get('shopware.statistic.tracer.cart_tracer');
        $context = Shopware()->Container()->get('storefront.context.service')->getShopContext();

        $this->insertCart(self::SESSION_ID, 'checkout', self::USER_AGENT, 1);

        $tracer->traceRequest($request, $context);

        $this->assertEquals(
            [
                'sessionID' => self::SESSION_ID,
                'useragent' => '',
                'userID' => '1',
                'lastviewport' => 'checkout',
            ],
            $this->getCart(self::SESSION_ID)
        );
    }

    public function testControllerSwitch()
    {
        $request = $this->getRequest('checkout', self::USER_AGENT);
        $this->prepareSession(self::SESSION_ID, ['sUserId' => 1]);

        /** @var CartTracer $tracer */
        $tracer = Shopware()->Container()->get('shopware.statistic.tracer.cart_tracer');
        $context = Shopware()->Container()->get('storefront.context.service')->getShopContext();

        $this->insertCart(self::SESSION_ID, 'detail', self::USER_AGENT, 1);

        $tracer->traceRequest($request, $context);

        $this->assertEquals(
            [
                'sessionID' => self::SESSION_ID,
                'useragent' => self::USER_AGENT,
                'userID' => '1',
                'lastviewport' => 'checkout',
            ],
            $this->getCart(self::SESSION_ID)
        );
    }

    public function testCustomerLogin()
    {
        $request = $this->getRequest('checkout', self::USER_AGENT);
        $this->prepareSession(self::SESSION_ID, ['sUserId' => 1]);

        /** @var CartTracer $tracer */
        $tracer = Shopware()->Container()->get('shopware.statistic.tracer.cart_tracer');
        $context = Shopware()->Container()->get('storefront.context.service')->getShopContext();

        $this->insertCart(self::SESSION_ID, 'checkout', self::USER_AGENT, 0);

        $tracer->traceRequest($request, $context);

        $this->assertEquals(
            [
                'sessionID' => self::SESSION_ID,
                'useragent' => self::USER_AGENT,
                'userID' => '1',
                'lastviewport' => 'checkout',
            ],
            $this->getCart(self::SESSION_ID)
        );
    }

    public function testOnlyUpdateOwnSession()
    {
        $request = $this->getRequest('blog', self::USER_AGENT);
        $this->prepareSession(self::SESSION_ID, ['sUserId' => 1]);

        /** @var CartTracer $tracer */
        $tracer = Shopware()->Container()->get('shopware.statistic.tracer.cart_tracer');
        $context = Shopware()->Container()->get('storefront.context.service')->getShopContext();

        $this->insertCart(self::SESSION_ID, 'checkout', self::USER_AGENT, 0);

        //shouldn't be updated
        $this->insertCart(self::SESSION_ID . '-1', 'detail', self::USER_AGENT . '-1', 2);
        $this->insertCart(self::SESSION_ID . '-2', 'listing', self::USER_AGENT . '-2', 3);

        $tracer->traceRequest($request, $context);

        $this->assertEquals(
            [
                'sessionID' => self::SESSION_ID,
                'useragent' => self::USER_AGENT,
                'userID' => '1',
                'lastviewport' => 'blog',
            ],
            $this->getCart(self::SESSION_ID)
        );

        $this->assertEquals(
            [
                'sessionID' => self::SESSION_ID . '-1',
                'useragent' => self::USER_AGENT . '-1',
                'userID' => '2',
                'lastviewport' => 'detail',
            ],
            $this->getCart(self::SESSION_ID . '-1')
        );

        $this->assertEquals(
            [
                'sessionID' => self::SESSION_ID . '-2',
                'useragent' => self::USER_AGENT . '-2',
                'userID' => '3',
                'lastviewport' => 'listing',
            ],
            $this->getCart(self::SESSION_ID . '-2')
        );
    }

    public function testTraceWithoutSessionId()
    {
        $request = $this->getRequest('checkout', self::USER_AGENT);
        $this->prepareSession('');

        /** @var CartTracer $tracer */
        $tracer = Shopware()->Container()->get('shopware.statistic.tracer.cart_tracer');
        $context = Shopware()->Container()->get('storefront.context.service')->getShopContext();

        $this->insertCart(self::SESSION_ID, 'detail', self::USER_AGENT, 10);

        $tracer->traceRequest($request, $context);

        $this->assertEquals(
            [
                'sessionID' => self::SESSION_ID,
                'useragent' => self::USER_AGENT,
                'userID' => '10',
                'lastviewport' => 'detail',
            ],
            $this->getCart(self::SESSION_ID)
        );
    }

    private function insertCart(string $sessionId, ?string $controller, string $userAgent, int $customerId)
    {
        $this->connection->insert('s_order_basket', [
            'sessionID' => $sessionId,
            'userID' => $customerId,
            'lastviewport' => $controller,
            'useragent' => $userAgent,
        ]);
    }

    private function getCart($session)
    {
        return $this->connection->fetchAssoc(
            'SELECT sessionID, userID, lastviewport, useragent FROM s_order_basket WHERE sessionID = :id',
            [':id' => $session]
        );
    }

    private function getRequest(
        ?string $controller,
        string $userAgent,
        array $parameters = []
    ): \Enlight_Controller_Request_RequestHttp {
        $request = new \Enlight_Controller_Request_RequestHttp();

        $request->setControllerName($controller);
        foreach ($parameters as $key => $value) {
            $request->setParam($key, $value);
        }

        $_SERVER['HTTP_USER_AGENT'] = $userAgent;

        return $request;
    }

    private function prepareSession(string $id, array $values = []): void
    {
        /** @var \Enlight_Components_Session_Namespace $session */
        $session = Shopware()->Container()->get('session');

        $session->offsetSet('sessionId', $id);

        foreach ($values as $key => $value) {
            $session->offsetSet($key, $value);
        }
    }
}
