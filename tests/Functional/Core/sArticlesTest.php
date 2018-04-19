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
 * Shopware SwagAboCommerce Plugin - Bootstrap
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class sArticlesTest extends Enlight_Components_Test_Controller_TestCase
{
    public function testCanInstanciatesArticles()
    {
        $sArticles = new sArticles();
        $categoryId = Shopware()->Shop()->getCategory()->getId();
        $translationId = (!Shopware()->Shop()->getDefault() ? Shopware()->Shop()->getId() : null);
        $customerGroupId = ((int) Shopware()->Modules()->System()->sUSERGROUPDATA['id']);

        $this->assertsArticlesState($sArticles, $categoryId, $translationId, $customerGroupId);
    }

    public function testCanInjectParameters()
    {
        $category = new \Shopware\Models\Category\Category();

        $categoryId = 1;
        $translationId = 12;
        $customerGroupId = 23;

        $category->setPrimaryIdentifier($categoryId);
        $sArticles = new sArticles($category, $translationId, $customerGroupId);

        $this->assertsArticlesState($sArticles, $categoryId, $translationId, $customerGroupId);
    }

    /**
     * Checks if price group is taken into account correctly
     *
     * @ticket SW-4887
     */
    public function testPriceGroupForMainVariant()
    {
        // Add price group
        $sql = '
        UPDATE s_articles SET pricegroupActive = 1 WHERE id = 2;
        INSERT INTO s_core_pricegroups_discounts (`groupID`, `customergroupID`, `discount`, `discountstart`) VALUES (1, 1, 5, 1);
        ';

        Shopware()->Db()->query($sql);

        $this->dispatch('/');

        Shopware()->Container()->get('storefront.context.service')->refresh();
        Shopware()->Container()->get('storefront.context.service')->getShopContext();

        $correctPrice = '18,99';
        $article = Shopware()->Modules()->Articles()->sGetArticleById(
            2
        );
        $this->assertEquals($correctPrice, $article['price']);

        // delete price group
        $sql = '
        UPDATE s_articles SET pricegroupActive = 0 WHERE id = 2;
        DELETE FROM s_core_pricegroups_discounts WHERE `customergroupID` = 1 AND `discount` = 5;
        ';
        Shopware()->Db()->query($sql);
    }

    /**
     * @ticket SW-5391
     */
    public function testsGetPromotionByIdWithNonExistingArticle()
    {
        $result = Shopware()->Modules()->Articles()->sGetPromotionById('fix', 0, 9999999);

        // a query to a not existing article should return 'false' and not throw an exception
        $this->assertFalse($result);
    }

    protected function assertsArticlesState($sArticles, $categoryId, $translationId, $customerGroupId)
    {
        $this->assertInstanceOf('Shopware\Models\Category\Category', $this->readAttribute($sArticles, 'category'));
        $this->assertEquals($categoryId, $this->readAttribute($sArticles, 'categoryId'));
        $this->assertEquals($translationId, $this->readAttribute($sArticles, 'translationId'));
        $this->assertEquals($customerGroupId, $this->readAttribute($sArticles, 'customerGroupId'));
    }
}
