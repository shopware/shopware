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

namespace Shopware\Tests\Functional\Bundle\StoreFrontBundle\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Bundle\StoreFrontBundle\AdvancedMenu\AdvancedMenuService;
use Shopware\Bundle\StoreFrontBundle\Category\CategoryServiceInterface;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopScope;
use Shopware\Tests\Functional\DataGenerator\CategoryDataGenerator;

/**
 * @group AdvancedMenu
 */
class AdvancedMenuServiceTest extends TestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var int
     */
    private $mainCategoryId;

    /**
     * @var CategoryServiceInterface
     */
    private $categoryService;

    /**
     * @var AdvancedMenuService
     */
    private $reader;

    /**
     * @var \Shopware\Context\Struct\ShopContext
     */
    private $context;

    /**
     * @var CategoryDataGenerator
     */
    private $dataGenerator;

    public function setUp()
    {
        $this->connection = Shopware()->Container()->get('dbal_connection');
        $this->connection->beginTransaction();

        $this->dataGenerator = new CategoryDataGenerator();

        $this->reader = Shopware()->Container()->get('storefront.advanced_menu.service');
        $this->categoryService = Shopware()->Container()->get('storefront.category.service');

        $this->context = Shopware()->Container()->get('storefront.context.factory')->create(
            new ShopScope(1),
            new CustomerScope(null, 'EK'),
            new CheckoutScope()
        );

        $this->connection->insert('s_categories', [
            'parent' => 1,
            'description' => 'MainCategory',
            'active' => 1,
        ]);
        $this->mainCategoryId = $this->connection->lastInsertId('s_categories');

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testGetAdvancedMenu()
    {
        $categories = [
            [
                'name' => 'first level',
                'children' => [
                    ['name' => 'second level 01'],
                    ['name' => 'second level 02'],
                    [
                        'name' => 'second level 03',
                        'children' => [
                            ['name' => 'third level 01'],
                            ['name' => 'third level 02'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'first level 02',
                'children' => [
                    ['name' => 'second level 04'],
                    ['name' => 'second level 05'],
                    ['name' => 'second level 06'],
                ],
            ],
        ];

        $this->context->getShop()->setCategory(
            array_shift($this->categoryService->getList([$this->mainCategoryId], $this->context))
        );

        $this->dataGenerator->saveTree($categories, [$this->mainCategoryId]);

        $menu = $this->reader->get($this->context, 3);

        $menu = json_decode(json_encode($menu->getTree($this->mainCategoryId)), true);

        $menu = $this->extractTree($menu);

        $this->assertSame($categories, $menu);
    }

    public function testAdvancedMenuWithNestedSystemCategory()
    {
        $categories = [
            [
                'name' => 'first level',
                'children' => [
                    ['name' => 'second level'],
                    ['name' => 'second level'],
                    [
                        'name' => 'third level',
                        'children' => [
                            ['name' => 'fourth level'],
                            [
                                'name' => 'fourth level 02',
                                'children' => [
                                    ['name' => 'fifth level'],
                                    ['name' => 'fifth level 02'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tree = $this->dataGenerator->saveTree($categories, [$this->mainCategoryId]);

        $this->context->getShop()->setCategory(
            //set "fourth level" category as system category
            array_shift($this->categoryService->getList([(int) $tree[0]['children'][2]['id']], $this->context))
        );

        $menu = $this->reader->get($this->context, 1);

        $menu = json_decode(json_encode($menu->getTree((int) $tree[0]['children'][2]['id'])), true);

        $menu = $this->extractTree($menu);

        $this->assertSame(
            [
                ['name' => 'fourth level'],
                ['name' => 'fourth level 02'],
            ],
            $menu
        );
    }

    public function testGetAdvancedMenuWithInactiveCategory()
    {
        $categories = [
            [
                'name' => 'first level 01',
                'children' => [
                    ['name' => 'second level 01'],
                    ['name' => 'second level 02'],
                    [
                        'name' => 'second level 03',
                        'active' => 0,
                        'children' => [
                            ['name' => 'third level'],
                            [
                                'name' => 'third level 02',
                                'children' => [
                                    ['name' => 'fourth level'],
                                    ['name' => 'fourth level 02'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'first level 02',
                'children' => [
                    ['name' => 'second level 04'],
                    ['name' => 'second level 05'],
                    ['name' => 'second level 06'],
                ],
            ],
        ];

        $this->dataGenerator->saveTree($categories, [$this->mainCategoryId]);

        $this->context->getShop()->setCategory(
            array_shift($this->categoryService->getList([$this->mainCategoryId], $this->context))
        );

        $menu = $this->reader->get($this->context, 2);

        $tree = $menu->getTree((int) $this->mainCategoryId);

        $menu = json_decode(json_encode($tree), true);

        $menu = $this->extractTree($menu);

        $this->assertSame(
            [
                [
                    'name' => 'first level 01',
                    'children' => [
                        ['name' => 'second level 01'],
                        ['name' => 'second level 02'],
                    ],
                ],
                [
                    'name' => 'first level 02',
                    'children' => [
                        ['name' => 'second level 04'],
                        ['name' => 'second level 05'],
                        ['name' => 'second level 06'],
                    ],
                ],
            ],
            $menu
        );
    }

    /**
     * Resolves advancedMenu data structure to the simple nested tree array
     *
     * @param array[] $menu
     *
     * @return array[]
     */
    private function extractTree(array $menu): array
    {
        $result = [];
        foreach ($menu as $item) {
            $new = ['name' => $item['name']];
            if ($item['children']) {
                $new['children'] = $this->extractTree($item['children']);
            }
            $result[] = $new;
        }

        return $result;
    }
}
