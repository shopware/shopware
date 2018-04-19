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

namespace Shopware\Tests\Unit\Components\LegacyRequestWrapper;

use PHPUnit\Framework\TestCase;

class GetWrapperTest extends TestCase
{
    /**
     * @var \Enlight_Controller_Request_RequestTestCase
     */
    private $request;

    /**
     * @var \sSystem
     */
    private $system;

    public function setUp()
    {
        $this->request = new \Enlight_Controller_Request_RequestTestCase();
        $this->system = new \sSystem($this->request);
    }

    public function tearDown()
    {
        $this->request->clearAll();
    }

    public function testSet()
    {
        $this->system->_GET->offsetSet('foo', 'bar');
        $this->assertEquals('bar', $this->request->getQuery('foo'));

        $this->system->_GET->offsetSet('foo', null);
        $this->assertNull($this->request->getQuery('bar'));

        $this->system->_GET->offsetSet('foo', []);
        $this->assertEmpty($this->request->getQuery('bar'));
        $this->assertInternalType('array', $this->request->getQuery('foo'));
    }

    public function testGet()
    {
        $this->request->setQuery('foo', 'bar');
        $this->assertEquals('bar', $this->system->_GET->offsetGet('foo'));

        $this->request->setQuery('foo', null);
        $this->assertNull($this->system->_GET->offsetGet('bar'));

        $this->request->setQuery('foo', []);
        $this->assertEmpty($this->system->_GET->offsetGet('bar'));
        $this->assertInternalType('array', $this->system->_GET->offsetGet('foo'));
    }

    public function testUnset()
    {
        $this->system->_GET->offsetSet('foo', 'bar');
        $this->assertEquals('bar', $this->request->getQuery('foo'));
        unset($this->system->_GET['foo']);
        $this->assertNull($this->request->getQuery('foo'));
    }

    public function testSetAll()
    {
        $this->system->_GET->offsetSet('foo', 'bar');
        $this->assertEquals('bar', $this->request->getQuery('foo'));

        $this->system->_GET = ['foo' => 'too'];
        $this->assertNull($this->request->getQuery('bar'));
        $this->assertEquals('too', $this->request->getQuery('foo'));
    }

    public function testToArray()
    {
        $this->request->setQuery('foo', 'bar');
        $this->assertEquals(['foo' => 'bar'], $this->system->_GET->toArray());
    }
}
