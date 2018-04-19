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

namespace Shopware\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class EnlightLoaderTest extends TestCase
{
    /**
     * Test enlight loader check file
     */
    public function testEnlightLoaderCheckFile()
    {
        $this->assertTrue(\Enlight_Loader::checkFile('H:\Apache Group\Apache\htdocs\shopware.php'));
        $this->assertFalse(\Enlight_Loader::checkFile('H:\Apache Group\Apache\htdocs\shopware.php' . "\0"));
    }

    /**
     *  Test case method
     */
    public function testAddIncludePath()
    {
        $old = \Enlight_Loader::addIncludePath('.');
        $new = \Enlight_Loader::explodeIncludePath();
        $last = array_pop($new);

        \Enlight_Loader::setIncludePath($old);

        $this->assertEquals('.', $last);
    }

    /**
     *  Test case method
     */
    public function testAddIncludePath2()
    {
        $old = \Enlight_Loader::addIncludePath('.', \Enlight_Loader::POSITION_PREPEND);
        $new = \Enlight_Loader::explodeIncludePath();
        $first = array_shift($new);

        \Enlight_Loader::setIncludePath($old);

        $this->assertEquals('.', $first);
    }

    /**
     *  Test case method
     */
    public function testAddIncludePath3()
    {
        $old = \Enlight_Loader::addIncludePath('.', \Enlight_Loader::POSITION_REMOVE);
        $new = \Enlight_Loader::explodeIncludePath();
        $found = array_search('.', $new, true);

        \Enlight_Loader::setIncludePath($old);

        $this->assertFalse($found);
    }

    /**
     * Test realpath abstraction
     *
     * @dataProvider dataProviderRealpath
     */
    public function testRealpath($path, $expected)
    {
        $oldCWD = getcwd();
        chdir(__DIR__);

        $result = \Enlight_Loader::realpath($path);
        $this->assertEquals($expected, $result);

        chdir($oldCWD);
    }

    /**
     * Provide test cases
     *
     * @return array
     */
    public function dataProviderRealpath()
    {
        return [
            // Nonexisting paths
            ['/nonexisting', false],
            ['../nonexisting', false],
            ['nonexisting', false],
            [' ', false],

            // Relative paths
            ['', __DIR__],
            ['./', __DIR__],
            ['../', dirname(__DIR__)],
            ['Bundle/MediaBundle/Strategy/../../', __DIR__ . '/Bundle'],

            // Absolute paths
            ['/', '/'],
            [__DIR__ . '/', __DIR__],
            [__DIR__ . '/tests/..', __DIR__],
        ];
    }
}
