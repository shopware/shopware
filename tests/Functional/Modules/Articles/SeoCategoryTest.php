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
class Shopware_Tests_Modules_Articles_SeoCategoryTest extends Enlight_Components_Test_Plugin_TestCase
{
    /**
     * @var \Shopware\Components\Api\Resource\Article
     */
    private $resource;

    public function setUp()
    {
        Shopware()->Container()->get('models')->clear();
        $this->resource = new \Shopware\Components\Api\Resource\Article();
        $this->resource->setManager(Shopware()->Models());
        parent::setUp();
    }

    public function testSeoCategory()
    {
        $this->dispatch('/');

        $data = $this->getSimpleTestData();

        $data['categories'] = Shopware()->Db()->fetchAll('SELECT DISTINCT id FROM s_categories LIMIT 5, 10');

        $first = $data['categories'][3];
        $second = $data['categories'][4];

        $data['seoCategories'] = [
            ['shopId' => 1, 'categoryId' => $first['id']],
            ['shopId' => 2, 'categoryId' => $second['id']],
        ];

        $article = $this->resource->create($data);

        $this->resource->setResultMode(Shopware\Components\Api\Resource\Resource::HYDRATE_OBJECT);

        /** @var $article Shopware\Models\Article\Article */
        $article = $this->resource->getOne($article->getId());

        $german = Shopware()->Modules()->Categories()->sGetCategoryIdByArticleId(
            $article->getId(),
            null,
            1
        );

        $english = Shopware()->Modules()->Categories()->sGetCategoryIdByArticleId(
            $article->getId(),
            null,
            2
        );

        $this->assertEquals($first['id'], $german);
        $this->assertEquals($second['id'], $english);
    }

    private function getSimpleTestData()
    {
        return [
            'name' => 'Testartikel',
            'description' => 'Test description',
            'active' => true,
            'mainDetail' => [
                'number' => 'swTEST' . uniqid(rand()),
                'inStock' => 15,
                'unitId' => 1,
                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'from' => 1,
                        'to' => '-',
                        'price' => 400,
                    ],
                ],
            ],
            'taxId' => 1,
            'supplierId' => 2,
        ];
    }
}
