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

use Shopware\Tests\Functional\DataGenerator\CategoryDataGenerator;

class Shopware_Tests_Controllers_Widgets_AdvancedMenuTest extends Enlight_Components_Test_Controller_TestCase
{
    /**
     * @group AdvancedMenu
     */
    public function testAdvancedMenu()
    {
        $connection = Shopware()->Container()->get('dbal_connection');
        $connection->beginTransaction();

        $this->initializeAdvancedMenuContext();

        $controller = $this->createWidgetsAdvancedMenuController();

        $controller->indexAction();

        $assigns = $controller->View()->getAssign();

        //assert view assignments for advanced menu template
        $this->assertArrayHasKey('advancedMenu', $assigns);
        $this->assertNotEmpty($assigns['advancedMenu']);
        $this->assertArrayHasKey('columnAmount', $assigns);
        $this->assertArrayHasKey('hoverDelay', $assigns);

        //reset database and current context instance
        $connection->rollBack();
        Shopware()->Container()->get('storefront.context.service')->refresh();
    }

    /**
     * @return Shopware_Controllers_Widgets_AdvancedMenu
     */
    private function createWidgetsAdvancedMenuController(): Shopware_Controllers_Widgets_AdvancedMenu
    {
        $view = new Enlight_View_Default(Shopware()->Container()->get('template'));

        /** @var $controller */
        $proxy = Shopware()->Hooks()->getProxy('Shopware_Controllers_Widgets_AdvancedMenu');

        /** @var $controller Shopware_Controllers_Widgets_AdvancedMenu */
        $controller = new $proxy(new Enlight_Controller_Request_RequestHttp(),
            new Enlight_Controller_Response_ResponseHttp());
        $controller->setContainer(Shopware()->Container());
        $controller->setView($view);

        return $controller;
    }

    private function initializeAdvancedMenuContext(): void
    {
        $connection = Shopware()->Container()->get('dbal_connection');
        $connection->insert('s_categories', [
            'parent' => 1,
            'description' => 'MainCategory',
            'active' => 1,
        ]);
        $mainCategoryId = $connection->lastInsertId('s_categories');

        $generator = new CategoryDataGenerator();

        $generator->saveTree([
            ['name' => 'first level'],
            ['name' => 'first level 02'],
        ], [$mainCategoryId]);

        $context = Shopware()->Container()->get('storefront.context.service')->getShopContext();
        $category = Shopware()->Container()->get('storefront.category.service')->getList([$mainCategoryId], $context);
        $context->getShop()->setCategory(array_shift($category));
    }
}
