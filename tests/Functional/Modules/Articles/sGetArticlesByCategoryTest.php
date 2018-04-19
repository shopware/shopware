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
class Shopware_Tests_Modules_Articles_getListingArrayKeyTest extends Enlight_Components_Test_TestCase
{
    /**
     * Module instance
     *
     * @var sArticles
     */
    protected $module;

    /**
     * Test set up method
     */
    protected function setUp()
    {
        parent::setUp();
        $this->module = Shopware()->Modules()->Articles();
    }

    public function testGetArticles()
    {
        $categories = [5, 6, 8, 12, 13, 14, 15, 31];
        foreach ($categories as $id => $expected) {
            $data = $this->module->sGetArticlesByCategory($id);

            foreach ($data['sArticles'] as $key => $article) {
                $this->assertEquals($key, $article['ordernumber']);
            }
        }
    }
}
