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

namespace Shopware\Tests\Unit\Components\HttpCache;

use PHPUnit\Framework\TestCase;
use Shopware\Components\HttpCache\Store;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class StoreTest extends TestCase
{
    public function provideUrls()
    {
        return [
            ['http://example.com', 'http://example.com'],
            ['http://example.com?a=a', 'http://example.com?a=a'],
            ['http://example.com?z=a&a=a', 'http://example.com?a=a&z=a'],
            ['http://example.com?z=a&z=b', 'http://example.com?z=b'], // duplicate parameters
            ['http://example.com?Z=a&z=a', 'http://example.com?Z=a&z=a'], // case sensitive
            ['http://example.com/?colors[]=red&cars[]=Saab&cars[]=Audi&colors[]=red&colors[]=blue', 'http://example.com/?cars%5B0%5D=Saab&cars%5B1%5D=Audi&colors%5B0%5D=red&colors%5B1%5D=red&colors%5B2%5D=blue'],
        ];
    }

    /**
     * @dataProvider provideUrls
     *
     * @param string $url
     * @param string $expected
     */
    public function testSortQueryParams($url, $expected)
    {
        $object = $this->createPartialMock(Store::class, []);
        $class = new \ReflectionClass($object);
        $method = $class->getMethod('sortQueryStringParameters');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invokeArgs($object, [$url]));
    }
}
