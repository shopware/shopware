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

namespace Shopware\Tests\Unit\Components;

use PHPUnit\Framework\TestCase;
use Shopware\Components\QueryAliasMapper;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class QueryAliasMapperTest extends TestCase
{
    public function testCanBeInitializedByArray()
    {
        $mapping = [
            'foo' => 'bar',
            'omg' => 'baz',
        ];

        $mapper = new QueryAliasMapper($mapping);

        $this->assertEquals($mapping, $mapper->getQueryAliases());
    }

    public function testCanBeInitializedByString()
    {
        $expected = [
            'foo' => 'bar',
            'omg' => 'baz',
        ];

        $mapper = QueryAliasMapper::createFromString('foo=bar,omg=baz');

        $this->assertEquals($expected, $mapper->getQueryAliases());
    }

    public function testCanBeInitializedByConfig()
    {
        $expected = [
            'foo' => 'bar',
            'omg' => 'baz',
        ];

        $mock = $this->createConfiguredMock(
            \Shopware_Components_Config::class,
            ['get' => 'foo=bar,omg=baz']
        );

        $mapper = QueryAliasMapper::createFromConfig($mock);

        $this->assertEquals($expected, $mapper->getQueryAliases());
    }

    public function testAliasesCanBeRetrieved()
    {
        $mapping = [
            'sSearch' => 'q',
            'sPage' => 'p',
        ];

        $mapper = new QueryAliasMapper($mapping);

        $this->assertEquals('q', $mapper->getShortAlias('sSearch'));
        $this->assertEquals('p', $mapper->getShortAlias('sPage'));
        $this->assertEquals(null, $mapper->getShortAlias('somefoo'));
    }

    public function testLongParamsGettingReplaced()
    {
        $mapping = [
            'longParam0' => 'shortParam0',
            'longParam1' => 'shortParam1',
        ];

        $mapper = new QueryAliasMapper($mapping);

        $params = [
            'longParam0' => 'someValue',
            'longParam1' => 'someOtherValue',
            'someParam' => 'someValue',
        ];

        $result = $mapper->replaceLongParams($params);

        $expected = [
            'shortParam0' => 'someValue',
            'shortParam1' => 'someOtherValue',
            'someParam' => 'someValue',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testShortParamsGettingReplaced()
    {
        $mapping = [
            'longParam0' => 'shortParam0',
            'longParam1' => 'shortParam1',
        ];

        $mapper = new QueryAliasMapper($mapping);

        $params = [
            'shortParam0' => 'someValue',
            'shortParam1' => 'someOtherValue',
            'someParam' => 'someValue',
        ];

        $result = $mapper->replaceShortParams($params);

        $expected = [
            'longParam0' => 'someValue',
            'longParam1' => 'someOtherValue',
            'someParam' => 'someValue',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testRequestQueriesGettingReplacd()
    {
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setQuery('q', 'someValue');
        $request->setQuery('p', 'someOtherValue');
        $request->setQuery('someParam', 'someValue');

        $mapping = [
            'sSearch' => 'q',
            'sPage' => 'p',
        ];

        $mapper = new QueryAliasMapper($mapping);

        $mapper->replaceShortRequestQueries($request);

        $expected = [
            'someParam' => 'someValue',
            'sSearch' => 'someValue',
            'sPage' => 'someOtherValue',
        ];

        $this->assertEquals($expected, $request->getParams());
        $request->clearAll();
    }
}
