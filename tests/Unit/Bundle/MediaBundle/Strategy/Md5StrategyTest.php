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

namespace Shopware\Tests\Unit\Bundle\MediaBundle\Strategy;

use PHPUnit\Framework\TestCase;
use Shopware\Bundle\MediaBundle\Strategy\Md5Strategy;

class Md5StrategyTest extends TestCase
{
    /**
     * @var Md5Strategy
     */
    private $strategy;

    protected function setUp()
    {
        $this->strategy = new Md5Strategy();
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object     instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array  $parameters array of parameters to pass into method
     *
     * @return mixed method return
     */
    public function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @return array
     */
    public function getNormalizedData()
    {
        return [
            ['/media/image/Einkaufstasche.jpg', 'media/image/Einkaufstasche.jpg'],
            ['http://shopware.com/subfolder/shop/media/image/Einkaufstasche.jpg', 'media/image/Einkaufstasche.jpg'],
            ['/var/www/web1/shopware/media/image/Einkaufstasche.jpg', 'media/image/Einkaufstasche.jpg'],
            ['/var/www/web1/shopware/media/image/', '/var/www/web1/shopware/media/image/'],
            ['/var/www/web1/shopware/media/image/53/a4/d3/foo.jpg', 'media/image/foo.jpg'],
        ];
    }

    /**
     * @return array
     */
    public function getInvalidPathsToEncode()
    {
        return [
            ['media/image/53/'],
            ['media/image/53/fa'],
            ['media/image/53/fa/a3/'],
            ['media/image/53/fa/a3/foo'],
            ['media/image/53/fa/a3/foo.'],
            ['www.shopware.com/media/image/53/'],
            ['/var/www/local/media/image/53/'],
        ];
    }

    public function getSubstringPathDataSet()
    {
        return [
            ['media/image/f3/a2/ee/image.jpg', 'media/image/f3/a2/ee/image.jpg'],
            ['http://shop.internal/media/image/f3/a2/ee/image.jpg', 'media/image/f3/a2/ee/image.jpg'],
            ['media/media/image/f3/a2/ee/image.jpg', 'media/image/f3/a2/ee/image.jpg'],
        ];
    }

    public function getEncodeDataSet()
    {
        return [
            ['media/image/f3/aa/32/image.jpg', 'media/image/f3/aa/32/image.jpg'],
            ['media/media/image/f3/aa/32/image.jpg', 'media/image/f3/aa/32/image.jpg'],
            ['media/image/image.jpg', 'media/image/65/d9/11/image.jpg'],

            // implicit blacklist test
            ['media/image/1430.jpg', 'media/image/g0/bf/bf/1430.jpg'],
        ];
    }

    /**
     * @dataProvider getNormalizedData
     *
     * @param string $path
     * @param string $expected
     */
    public function testNormalizer($path, $expected)
    {
        $this->assertEquals(
            $expected,
            $this->strategy->normalize($path)
        );
    }

    public function testEncodedPath()
    {
        $this->assertTrue($this->strategy->isEncoded('media/image/53/3d/af/my-image.png'));
        $this->assertTrue($this->strategy->isEncoded('http://www.shopware.com/media/image/53/3d/af/my-image.png'));
    }

    public function testNotEncodedPath()
    {
        $this->assertFalse($this->strategy->isEncoded('media/image/my-image.png'));
        $this->assertFalse($this->strategy->isEncoded('media/image/53/'));
        $this->assertFalse($this->strategy->isEncoded('media/image/53/foo'));
        $this->assertFalse($this->strategy->isEncoded('media/image/53/a4/d3/'));
        $this->assertFalse($this->strategy->isEncoded('media/image/53/a4/d3/foo'));
        $this->assertFalse($this->strategy->isEncoded('http://www.shopware.com/media/image/53/'));
    }

    /**
     * @dataProvider getInvalidPathsToEncode
     *
     * @param string $path
     */
    public function testEncodingWithInvalidPaths($path)
    {
        $this->assertEquals('', $this->strategy->encode($path));
    }

    public function testEncodingBlacklist()
    {
        $this->assertFalse($this->invokeMethod($this->strategy, 'isEncoded', ['media/image/f1/d3/ad/foo.jpg']));
    }

    /**
     * @dataProvider getSubstringPathDataSet
     *
     * @param string $path
     * @param string $expectedPath
     */
    public function testSubstringPath($path, $expectedPath)
    {
        $this->assertEquals($expectedPath, $this->invokeMethod($this->strategy, 'substringPath', [$path]));
    }

    /**
     * @dataProvider getEncodeDataSet
     *
     * @param string $path
     * @param string $expectedPath
     */
    public function testEncode($path, $expectedPath)
    {
        $this->assertEquals($expectedPath, $this->strategy->encode($path));
    }
}
