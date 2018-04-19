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

namespace Shopware\Tests\Unit\Controller\Backend;

use PHPUnit\Framework\TestCase;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ExtJsTest extends TestCase
{
    public function exampleData()
    {
        return [
            [['module', 'controller', 'file'], 'module/controller/file.js'],
            [['module', 'controller', 'file.js'], 'module/controller/file.js.js'],
            [['module', '', 'file'], 'module//file.js'],
            [['Module', 'Controller', 'File'], 'module/controller/file.js'],
            [['MoDule', 'ContRoller', 'FiLe'], 'mo_dule/cont_roller/fi_le.js'],
            [['MOdule', 'ContRoller', 'FiLe'], 'm_odule/cont_roller/fi_le.js'],
            [['MODUle', 'ContRoller', 'FiLe'], 'mod_ule/cont_roller/fi_le.js'],
        ];
    }

    /**
     * @dataProvider exampleData
     *
     * @param array  $args
     * @param string $expectedResult
     */
    public function testInflectPath($args, $expectedResult)
    {
        $SUT = $this->createPartialMock(\Shopware_Controllers_Backend_ExtJs::class, []);

        $class = new \ReflectionClass($SUT);
        $method = $class->getMethod('inflectPath');
        $method->setAccessible(true);

        $this->assertSame(
            $expectedResult,
            $method->invokeArgs($SUT, $args)
        );
    }
}
