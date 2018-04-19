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

namespace Shopware\Tests\Functional\Components\Api;

use Shopware\Components\Api\Resource\Cache;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CacheTest extends TestCase
{
    /**
     * @var Cache
     */
    protected $resource;

    protected function setUp()
    {
        parent::setUp();

        $httpCacheDir = Shopware()->Container()->getParameter('shopware.httpCache.cache_dir');
        $templateCacheDir = Shopware()->Container()->getParameter('shopware.template.cacheDir');

        @mkdir($httpCacheDir, 0777, true);
        @mkdir($templateCacheDir, 0777, true);
    }

    /**
     * @return Cache
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return Cache
     */
    public function createResource()
    {
        $resource = new Cache();
        $resource->setContainer(Shopware()->Container());

        return $resource;
    }

    /**
     * Check if listing all caches works
     */
    public function testGetListShouldBeSuccessFull()
    {
        $caches = $this->getResource()->getList();

        $this->assertEquals(6, count($caches['data']));
    }

    /**
     * Check if reading template cache infos works
     */
    public function testGetOneShouldBeSuccessFull()
    {
        $info = $this->getResource()->getOne('template');
        $this->assertEquals($info['id'], 'template');
    }

    /**
     * Check if clearing the template cache is successfull
     */
    public function testClearTemplateCacheShouldBeSuccessFull()
    {
        $this->getResource()->delete('template');

        $info = $this->getResource()->getOne('template');
        $this->assertEquals(0, $info['files']);
    }

    /**
     * Check if clearing the template cache is successfull
     */
    public function testClearHttpCacheShouldBeSuccessFull()
    {
        $this->getResource()->delete('http');
        $info = $this->getResource()->getOne('http');
        $this->assertEquals(0, $info['files']);

        $this->getResource()->delete('template');
        $info = $this->getResource()->getOne('template');
        $this->assertEquals(0, $info['files']);
    }
}
