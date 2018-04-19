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

namespace Shopware\Tests\Functional\Plugins\Core\RestApi\Componets;

use ShopwarePlugins\RestApi\Components\Router;

class RouterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Router
     */
    private $router;

    public function setUp()
    {
        $helper = Shopware();
        $loader = $helper->Container()->get('loader');

        $pluginDir = $helper->DocPath() . 'engine/Shopware/Plugins/Default/Core/RestApi';

        $loader->registerNamespace(
            'ShopwarePlugins\\RestApi\\Components',
            $pluginDir . '/Components/'
        );

        $this->router = new Router();
    }

    public function testCanGetInstance()
    {
        $this->assertInstanceOf('ShopwarePlugins\RestApi\Components\Router', $this->router);
    }

    public function routeGetProvider()
    {
        return [
            ['/api/articles/', 1, 'articles', 'index', false],
            ['/api/v1/articles/', 1, 'articles', 'index',  false],
            ['/api/v2/articles/', 2, 'articles', 'index', false],
            ['/api/articles/5', 1, 'articles', 'get', 5],
            ['/api/articles/sw123', 1, 'articles', 'get', 'sw123'],
            ['/api/v1/articles/5', 1, 'articles', 'get', 5],
            ['/api/v2/articles/5', 2, 'articles', 'get', 5],
        ];
    }

    /**
     * @covers \ShopwarePlugins\RestApi\Components\Router::assembleRoute
     * @dataProvider routeGetProvider
     */
    public function testGetRoutes($uri, $expectedVersion, $expectedController, $expectedAction, $expectedId)
    {
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setMethod('GET');

        $response = new \Enlight_Controller_Response_ResponseTestCase();

        $request->setPathInfo($uri);
        $this->router->assembleRoute($request, $response);

        $this->assertEquals($expectedController, $request->getControllerName());
        $this->assertEquals($expectedAction, $request->getActionName());
        $this->assertEquals($expectedVersion, $request->getParam('version'));
        $this->assertEquals($expectedId, $request->getParam('id'));
        $this->assertEquals(200, $response->getHttpResponseCode());
    }

    public function routePostProvider()
    {
        return [
            ['/api/articles/',     1, 'articles', 'post', false],
            ['/api/v1/articles/',  1, 'articles', 'post', false],
            ['/api/v2/articles/',  2, 'articles', 'post', false],
            ['/api/articles/5',    1, 'articles', 'post', 5],
            ['/api/v1/articles/5', 1, 'articles', 'post', 5],
            ['/api/v2/articles/5', 2, 'articles', 'post', 5],
        ];
    }

    /**
     * @covers \ShopwarePlugins\RestApi\Components\Router::assembleRoute
     * @dataProvider routePostProvider
     */
    public function testPostRoutes($uri, $expectedVersion, $expectedController, $expectedAction, $expectedId)
    {
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setMethod('POST');

        $response = new \Enlight_Controller_Response_ResponseTestCase();

        $request->setPathInfo($uri);
        $this->router->assembleRoute($request, $response);

        $this->assertEquals($expectedController, $request->getControllerName());
        $this->assertEquals($expectedAction, $request->getActionName());
        $this->assertEquals($expectedVersion, $request->getParam('version'));
        $this->assertEquals($expectedId, $request->getParam('id'));
        $this->assertEquals(201, $response->getHttpResponseCode());
    }

    public function routePutProvider()
    {
        return [
            ['/api/articles/',     1, 'articles', 'batch',  false, 200],
            ['/api/v1/articles/',  1, 'articles', 'batch',  false, 200],
            ['/api/v2/articles/',  2, 'articles', 'batch',  false, 200],
            ['/api/articles/5',    1, 'articles', 'put',      5,     200],
            ['/api/v1/articles/5', 1, 'articles', 'put',      5,     200],
            ['/api/v2/articles/5', 2, 'articles', 'put',      5,     200],
        ];
    }

    /**
     * @covers \ShopwarePlugins\RestApi\Components\Router::assembleRoute
     * @dataProvider routePutProvider
     */
    public function testPutRoutes($uri, $expectedVersion, $expectedController, $expectedAction, $expectedId, $expectedCode)
    {
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setMethod('PUT');

        $response = new \Enlight_Controller_Response_ResponseTestCase();

        $request->setPathInfo($uri);
        $this->router->assembleRoute($request, $response);

        $this->assertEquals($expectedController, $request->getControllerName());
        $this->assertEquals($expectedAction, $request->getActionName());
        $this->assertEquals($expectedVersion, $request->getParam('version'));
        $this->assertEquals($expectedId, $request->getParam('id'));
        $this->assertEquals($expectedCode, $response->getHttpResponseCode());
    }

    public function routeDeleteProvider()
    {
        return [
            ['/api/articles/',    1, 'articles', 'batchDelete', false, 200],
            ['/api/v1/articles/', 1, 'articles', 'batchDelete', false, 200],
            ['/api/v2/articles/', 2, 'articles', 'batchDelete', false, 200],

            ['/api/articles/5',    1, 'articles', 'delete', 5, 200],
            ['/api/v1/articles/5', 1, 'articles', 'delete', 5, 200],
            ['/api/v2/articles/5', 2, 'articles', 'delete', 5, 200],
        ];
    }

    /**
     * @covers \ShopwarePlugins\RestApi\Components\Router::assembleRoute
     * @dataProvider routeDeleteProvider
     */
    public function testDeleteRoutes($uri, $expectedVersion, $expectedController, $expectedAction, $expectedId, $expectedCode)
    {
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setMethod('DELETE');

        $response = new \Enlight_Controller_Response_ResponseTestCase();

        $request->setPathInfo($uri);
        $this->router->assembleRoute($request, $response);

        $this->assertEquals($expectedController, $request->getControllerName());
        $this->assertEquals($expectedAction, $request->getActionName());
        $this->assertEquals($expectedVersion, $request->getParam('version'));
        $this->assertEquals($expectedId, $request->getParam('id'));
        $this->assertEquals($expectedCode, $response->getHttpResponseCode());
    }
}
