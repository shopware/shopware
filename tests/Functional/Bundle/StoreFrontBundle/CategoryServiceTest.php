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

namespace Shopware\Tests\Functional\Bundle\StoreFrontBundle;

class CategoryServiceTest extends TestCase
{
    public function testCategorySorting()
    {
        $first = $this->helper->createCategory(['name' => 'first',  'parent' => 3]);
        $second = $this->helper->createCategory(['name' => 'second', 'parent' => $first->getId(), 'position' => 1]);
        $third = $this->helper->createCategory(['name' => 'third', 'parent' => $first->getId(), 'position' => 2]);
        $fourth = $this->helper->createCategory(['name' => 'fourth', 'parent' => $first->getId(), 'position' => 2]);

        $categories = Shopware()->Container()->get('storefront.category.service')->getList(
            [
                $second->getId(),
                $third->getId(),
                $fourth->getId(),
            ],
            $this->getContext()
        );

        foreach ($categories as $id => $category) {
            $this->assertEquals($id, $category->getId());
        }

        $categories = array_values($categories);
        $this->assertEquals($second->getId(), $categories[0]->getId());
        $this->assertEquals($third->getId(), $categories[1]->getId());
        $this->assertEquals($fourth->getId(), $categories[2]->getId());
    }

    public function testBlockedCustomerGroups()
    {
        $first = $this->helper->createCategory(['name' => 'first',  'parent' => 3]);
        $second = $this->helper->createCategory(['name' => 'second', 'parent' => $first->getId()]);
        $third = $this->helper->createCategory(['name' => 'third',   'parent' => $second->getId()]);

        $context = $this->getContext();

        Shopware()->Db()->query(
            'INSERT INTO s_categories_avoid_customergroups (categoryID, customerGroupID) VALUES (?, ?)',
            [$second->getId(), $context->getCurrentCustomerGroup()->getId()]
        );
        Shopware()->Db()->query(
            'INSERT INTO s_categories_avoid_customergroups (categoryID, customerGroupID) VALUES (?, ?)',
            [$third->getId(), $context->getCurrentCustomerGroup()->getId()]
        );

        $categories = Shopware()->Container()->get('storefront.category.service')->getList(
            [
                $first->getId(),
                $second->getId(),
                $third->getId(),
            ],
            $context
        );

        $this->assertCount(1, $categories);

        $this->assertArrayHasKey($first->getId(), $categories);
    }

    public function testOnlyActiveCategories()
    {
        $first = $this->helper->createCategory(['name' => 'first',  'parent' => 3, 'active' => false]);
        $second = $this->helper->createCategory(['name' => 'second', 'parent' => $first->getId(), 'active' => false]);
        $third = $this->helper->createCategory(['name' => 'third',   'parent' => $second->getId()]);

        $categories = Shopware()->Container()->get('storefront.category.service')->getList(
            [
                $first->getId(),
                $second->getId(),
                $third->getId(),
            ],
            $this->getContext()
        );

        $this->assertCount(1, $categories);
        $this->assertArrayHasKey($third->getId(), $categories);
    }
}
