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
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Controllers_Frontend_ListingTest extends Enlight_Components_Test_Controller_TestCase
{
    /**
     * Test the home redirect if the base category called directly
     * The request should return a 301 redirection to the base homepage.
     *
     * @ticket SW-11418
     */
    public function testHomeRedirect()
    {
        $mainCategory = Shopware()->Shop()->getCategory()->getId();

        $this->dispatch('/cat/index/sCategory/' . $mainCategory);

        $this->assertEquals(301, $this->Response()->getHttpResponseCode());
    }
}
