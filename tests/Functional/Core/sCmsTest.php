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

class sCmsTest extends Enlight_Components_Test_Controller_TestCase
{
    /**
     * @var sCms
     */
    private $module;

    public function setUp()
    {
        $this->Front()->setRequest($this->Request());
        $this->module = Shopware()->Modules()->Cms();
    }

    /**
     * @covers \sCms::sGetStaticPage
     */
    public function testsGetStaticPage()
    {
        // Without argument, returns false
        $this->assertFalse($this->module->sGetStaticPage());

        // Non-existent id returns false
        $this->assertFalse($this->module->sGetStaticPage(0));

        $pageIds = Shopware()->Db()->fetchCol('SELECT id FROM s_cms_static  LIMIT 10');

        foreach ($pageIds as $pageId) {
            $page = $this->module->sGetStaticPage($pageId);

            $this->assertArrayHasKey('id', $page);
            $this->assertArrayHasKey('description', $page);
            $this->assertArrayHasKey('html', $page);
            $this->assertArrayHasKey('grouping', $page);
            $this->assertArrayHasKey('position', $page);
            $this->assertArrayHasKey('link', $page);
            $this->assertArrayHasKey('page_title', $page);
            $this->assertArrayHasKey('meta_keywords', $page);
            $this->assertArrayHasKey('meta_description', $page);

            if (!empty($page['parentID'])) {
                $this->assertArrayHasKey('siblingPages', $page);
                foreach ($page['siblingPages'] as $siblingPage) {
                    $this->assertArrayHasKey('id', $siblingPage);
                    $this->assertArrayHasKey('description', $siblingPage);
                    $this->assertArrayHasKey('link', $siblingPage);
                    $this->assertArrayHasKey('target', $siblingPage);
                    $this->assertArrayHasKey('active', $siblingPage);
                    $this->assertArrayHasKey('page_title', $siblingPage);
                }
                $this->assertArrayHasKey('parent', $page);
                if (count($page['parent']) > 0) {
                    $this->assertArrayHasKey('id', $page['parent']);
                    $this->assertArrayHasKey('description', $page['parent']);
                    $this->assertArrayHasKey('link', $page['parent']);
                    $this->assertArrayHasKey('target', $page['parent']);
                    $this->assertArrayHasKey('page_title', $page['parent']);
                }
            } else {
                $this->assertArrayHasKey('subPages', $page);
                foreach ($page['subPages'] as $subPage) {
                    $this->assertArrayHasKey('id', $subPage);
                    $this->assertArrayHasKey('description', $subPage);
                    $this->assertArrayHasKey('link', $subPage);
                    $this->assertArrayHasKey('target', $subPage);
                    $this->assertArrayHasKey('page_title', $subPage);
                }
            }
        }
    }
}
