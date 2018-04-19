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

/**
 * @covers \Shopware\Components\LegacyRequestWrapper\GetWrapper
 */
class Shopware_Tests_Components_LegacyRequestWrapper_GetWrapperTest extends Enlight_Components_Test_Controller_TestCase
{
    private static $resources = [
        'Admin',
        'Articles',
        'Basket',
        'Categories',
        'cms',
        'Core',
        'Export',
        'Marketing',
        'Order',
        'RewriteTable',
    ];

    public function setUp()
    {
        parent::setUp();

        $this->dispatch('/');
    }

    /**
     * Tests that setting a value inside any core class is equivalent to setting it in the
     * global $_GET
     *
     * @return mixed
     */
    public function testSetQuery()
    {
        $previousGetData = Shopware()->Front()->Request()->getQuery();

        foreach (self::$resources as $name) {
            Shopware()->Front()->Request()->setQuery($name, $name . 'Value');
        }

        $getData = Shopware()->Front()->Request()->getQuery();
        $this->assertNotEquals($previousGetData, $getData);

        foreach (self::$resources as $name) {
            if (property_exists($name, 'sSYSTEM')) {
                $this->assertEquals($getData, Shopware()->Modules()->getModule($name)->sSYSTEM->_GET->toArray());
            }
        }

        return $getData;
    }

    /**
     * Tests that reseting GET data inside any core class is equivalent to resetting it in the
     * global $_GET
     *
     * @param $getData
     *
     * @return mixed
     * @depends testSetQuery
     */
    public function testOverwriteAndClearQuery($getData)
    {
        $this->assertNotEquals($getData, Shopware()->Front()->Request()->getQuery());

        foreach (self::$resources as $name) {
            if (property_exists($name, 'sSYSTEM')) {
                Shopware()->Front()->Request()->setQuery($getData);
                $this->assertEquals($getData, Shopware()->Modules()->getModule($name)->sSYSTEM->_GET->toArray());
                Shopware()->Modules()->getModule($name)->sSYSTEM->_GET = [];
                $this->assertNotEquals($getData, Shopware()->Modules()->getModule($name)->sSYSTEM->_GET->toArray());
            }
        }

        return $getData;
    }

    /**
     * Tests that getting GET data inside any core class is equivalent to getting it from the
     * global $_GET
     *
     * @depends testSetQuery
     */
    public function testGetQuery()
    {
        $previousGetData = Shopware()->Front()->Request()->getQuery();

        foreach (self::$resources as $name) {
            Shopware()->Modules()->getModule($name)->sSYSTEM->_GET[$name] = $name . 'Value';
        }

        $getData = Shopware()->Front()->Request()->getQuery();
        $this->assertNotEquals($previousGetData, $getData);

        foreach (self::$resources as $name) {
            if (property_exists($name, 'sSYSTEM')) {
                $this->assertEquals($getData, Shopware()->Modules()->getModule($name)->sSYSTEM->_GET->toArray());
            }
        }
    }
}
