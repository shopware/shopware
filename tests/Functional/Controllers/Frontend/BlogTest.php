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
 * @ticket SW-4724
 */
class Shopware_Tests_Controllers_Frontend_BlogTest extends Enlight_Components_Test_Plugin_TestCase
{
    /**
     * Set up test case, fix demo data where needed
     */
    public function setUp()
    {
        parent::setUp();

        $sql = "UPDATE `s_blog` SET `active` = '0' WHERE `id` =3;";
        Shopware()->Db()->exec($sql);
    }

    /**
     * Cleaning up testData
     */
    public function tearDown()
    {
        parent::tearDown();

        $sql = "UPDATE `s_blog` SET `active` = '1' WHERE `id` =3;";
        Shopware()->Db()->exec($sql);
    }

    /**
     * Tests the behavior if the blog article is not activated
     */
    public function testDispatchNoActiveBlogItem()
    {
        try {
            $this->dispatch('/blog/detail/?blogArticle=3');
        } catch (Exception $e) {
            $this->fail('Exception thrown. This should not occur.');
        }

        $this->assertTrue($this->Response()->isRedirect());
    }

    /**
     * Tests the behavior if the BlogItem does not exist anymore
     */
    public function testDispatchNotExistingBlogItem()
    {
        try {
            $this->dispatch('/blog/detail/?blogArticle=2222');
        } catch (Exception $e) {
            $this->fail('Exception thrown. This should not occur.');
        }

        $this->assertTrue($this->Response()->isRedirect());
    }

    /**
     * Test redirect when the blog category does not exist
     */
    public function testDispatchNotExistingBlogCategory()
    {
        try {
            $this->dispatch('/blog/?sCategory=17');
        } catch (Exception $e) {
            $this->fail('Exception thrown. This should not occur.');
        }

        $this->assertTrue(!$this->Response()->isRedirect());

        try {
            $this->dispatch('/blog/?sCategory=156165');
        } catch (Exception $e) {
            $this->fail('Exception thrown. This should not occur.');
        }

        $this->assertTrue($this->Response()->isRedirect());

        //deactivate blog category
        $sql = "UPDATE `s_categories` SET `active` = '0' WHERE `id` =17";
        Shopware()->Db()->exec($sql);

        //should be redirected because blog category is inactive
        try {
            $this->dispatch('/blog/?sCategory=17');
        } catch (Exception $e) {
            $this->fail('Exception thrown. This should not occur.');
        }
        $this->assertTrue($this->Response()->isRedirect());

        //should be redirected because blog category is inactive
        try {
            $this->dispatch('/blog/detail/?blogArticle=3');
        } catch (Exception $e) {
            $this->fail('Exception thrown. This should not occur.');
        }

        $this->assertTrue($this->Response()->isRedirect());

        //activate blog category
        $sql = "UPDATE `s_categories` SET `active` = '1' WHERE `id` =17";
        Shopware()->Db()->exec($sql);
    }
}
