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

namespace Shopware\Tests\Functional\Components\Api;

use Shopware\Components\Api\Resource\Article;
use Shopware\Components\Api\Resource\Resource;
use Shopware\Components\Api\Resource\Variant;
use Shopware\Models\Article\Configurator\Group;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class VariantTest extends TestCase
{
    /**
     * @var Variant
     */
    protected $resource;

    /**
     * @var Article
     */
    private $resourceArticle;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        Shopware()->Models()->clear();

        $this->resourceArticle = new Article();
        $this->resourceArticle->setAcl(Shopware()->Acl());
        $this->resourceArticle->setManager(Shopware()->Models());
    }

    /**
     * @return Variant
     */
    public function createResource()
    {
        return new Variant();
    }

    // Creates a article with variants
    public function testCreateShouldBeSuccessful()
    {
        // required field name is missing
        $testData = [
            'name' => 'Testartikel',
            'description' => 'Test description',
            'descriptionLong' => 'Test descriptionLong',
            'active' => true,
            'pseudoSales' => 999,
            'highlight' => true,
            'keywords' => 'test, testarticle',

            'mainDetail' => [
                'number' => 'swTEST' . uniqid(rand()),
                'inStock' => 15,
                'unitId' => 1,

                'attribute' => [
                    'attr1' => 'Freitext1',
                    'attr2' => 'Freitext2',
                ],

                'minPurchase' => 5,
                'purchaseSteps' => 2,

                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'from' => 1,
                        'to' => 20,
                        'price' => 500,
                    ],
                    [
                        'customerGroupKey' => 'EK',
                        'from' => 21,
                        'to' => '-',
                        'price' => 400,
                    ],
                ],
            ],

            'configuratorSet' => [
                'name' => 'MeinKonf',
                'groups' => [
                    [
                        'name' => 'Farbe',
                        'options' => [
                            ['name' => 'Gelb'],
                            ['name' => 'Grün'],
                        ],
                    ],
                    [
                        'name' => 'Gräße',
                        'options' => [
                            ['name' => 'L'],
                            ['name' => 'XL'],
                        ],
                    ],
                ],
            ],

            'variants' => [
                [
                    'number' => 'swTEST.variant.' . uniqid(rand()),
                    'inStock' => 17,
                    'unitId' => 1,

                    'attribute' => [
                        'attr3' => 'Freitext3',
                        'attr4' => 'Freitext4',
                    ],

                    'configuratorOptions' => [
                        [
                            'option' => 'Gelb',
                            'group' => 'Farbe',
                        ],
                        [
                            'option' => 'XL',
                            'group' => 'Größe',
                        ],
                    ],

                    'minPurchase' => 5,
                    'purchaseSteps' => 2,

                    'prices' => [
                        [
                            'customerGroupKey' => 'H',
                            'from' => 1,
                            'to' => 20,
                            'price' => 500,
                        ],
                        [
                            'customerGroupKey' => 'H',
                            'from' => 21,
                            'to' => '-',
                            'price' => 400,
                        ],
                    ],
                ],
                [
                    'number' => 'swTEST.variant.' . uniqid(rand()),
                    'inStock' => 17,
                    'unitId' => 1,

                    'attribute' => [
                        'attr3' => 'Freitext3',
                        'attr4' => 'Freitext4',
                    ],

                    'configuratorOptions' => [
                        [
                            'option' => 'Grün',
                            'group' => 'Farbe',
                        ],
                        [
                            'option' => 'XL',
                            'group' => 'Größe',
                        ],
                    ],

                    'minPurchase' => 5,
                    'purchaseSteps' => 2,

                    'prices' => [
                        [
                            'customerGroupKey' => 'H',
                            'from' => 1,
                            'to' => 20,
                            'price' => 500,
                        ],
                        [
                            'customerGroupKey' => 'H',
                            'from' => 21,
                            'to' => '-',
                            'price' => 400,
                        ],
                    ],
                ],
            ],

            'taxId' => 1,
            'supplierId' => 2,
        ];

        $article = $this->resourceArticle->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertGreaterThan(0, $article->getId());

        $this->assertEquals($article->getName(), $testData['name']);
        $this->assertEquals($article->getDescription(), $testData['description']);

        $this->assertEquals($article->getDescriptionLong(), $testData['descriptionLong']);
        $this->assertEquals($article->getMainDetail()->getAttribute()->getAttr1(), $testData['mainDetail']['attribute']['attr1']);
        $this->assertEquals($article->getMainDetail()->getAttribute()->getAttr2(), $testData['mainDetail']['attribute']['attr2']);

        $this->assertEquals($testData['taxId'], $article->getTax()->getId());

        $this->assertEquals(2, count($article->getMainDetail()->getPrices()));

        return $article;
    }

    /**
     * @depends testCreateShouldBeSuccessful
     * @expectedException \Shopware\Components\Api\Exception\CustomValidationException
     *
     * @param \Shopware\Models\Article\Article $article
     */
    public function testCreateWithExistingOrderNumberShouldThrowCustomValidationException(\Shopware\Models\Article\Article $article)
    {
        $testData = [
            'articleId' => $article->getId(),
            'number' => $article->getMainDetail()->getNumber(),
            'prices' => [
                [
                    'customerGroupKey' => 'EK',
                    'price' => 100,
                ],
            ],
        ];

        $this->resource->create($testData);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     *
     * @param \Shopware\Models\Article\Article $article
     *
     * @return \Shopware\Models\Article\Article
     */
    public function testGetOneShouldBeSuccessful(\Shopware\Models\Article\Article $article)
    {
        $this->resource->setResultMode(Variant::HYDRATE_OBJECT);

        /** @var $articleDetail \Shopware\Models\Article\Detail */
        foreach ($article->getDetails() as $articleDetail) {
            $articleDetailById = $this->resource->getOne($articleDetail->getId());
            $articleDetailByNumber = $this->resource->getOneByNumber($articleDetail->getNumber());

            $this->assertEquals($articleDetail->getId(), $articleDetailById->getId());
            $this->assertEquals($articleDetail->getId(), $articleDetailByNumber->getId());
        }

        return $article;
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetListShouldBeSuccessful()
    {
        $result = $this->resource->getList();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertGreaterThanOrEqual(1, $result['data']);
    }

    /**
     * @depends testGetOneShouldBeSuccessful
     *
     * @param $article\Shopware\Models\Article\Article
     */
    public function testDeleteShouldBeSuccessful($article)
    {
        $this->resource->setResultMode(Variant::HYDRATE_OBJECT);

        $deleteByNumber = true;

        /** @var $articleDetail \Shopware\Models\Article\Detail */
        foreach ($article->getDetails() as $articleDetail) {
            $deleteByNumber = !$deleteByNumber;

            if ($deleteByNumber) {
                $result = $this->resource->delete($articleDetail->getId());
            } else {
                $result = $this->resource->deleteByNumber($articleDetail->getNumber());
            }
            $this->assertInstanceOf('\Shopware\Models\Article\Detail', $result);
            $this->assertEquals(null, $result->getId());
        }

        // Delete the whole article at last
        $this->resourceArticle->delete($article->getId());
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testDeleteWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->delete(9999999);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testDeleteWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->delete('');
    }

    public function testVariantCreate()
    {
        $data = $this->getSimpleArticleData();
        $data['mainDetail'] = $this->getSimpleVariantData();
        $configuratorSet = $this->getSimpleConfiguratorSet();
        $data['configuratorSet'] = $configuratorSet;

        $article = $this->resourceArticle->create($data);
        $this->assertCount(0, $article->getDetails());

        $create = $this->getSimpleVariantData();
        $create['articleId'] = $article->getId();
        $create['configuratorOptions'] = $this->getVariantOptionsOfSet($configuratorSet);

        $variant = $this->resource->create($create);
        $this->assertCount(count($create['configuratorOptions']), $variant->getConfiguratorOptions());

        $create = $this->getSimpleVariantData();
        $create['articleId'] = $article->getId();
        $create['configuratorOptions'] = $this->getVariantOptionsOfSet($configuratorSet);
        $variant = $this->resource->create($create);
        $this->assertCount(count($create['configuratorOptions']), $variant->getConfiguratorOptions());

        $this->resourceArticle->setResultMode(Variant::HYDRATE_ARRAY);
        $id = $article->getId();
        $article = $this->resourceArticle->getOne($id);
        $this->assertCount(2, $article['details']);

        return $id;
    }

    /**
     * @depends testVariantCreate
     *
     * @param $articleId
     */
    public function testVariantUpdate($articleId)
    {
        $this->resourceArticle->setResultMode(Variant::HYDRATE_ARRAY);
        $article = $this->resourceArticle->getOne($articleId);

        foreach ($article['details'] as $variantData) {
            $updateData = [
                'articleId' => $articleId,
                'inStock' => 2000,
                'number' => $variantData['number'] . '-Updated',
                'unitId' => $this->getRandomId('s_core_units'),
            ];
            $variant = $this->resource->update($variantData['id'], $updateData);

            $this->assertEquals($variant->getUnit()->getId(), $updateData['unitId']);
            $this->assertEquals($variant->getInStock(), $updateData['inStock']);
            $this->assertEquals($variant->getNumber(), $updateData['number']);
        }
    }

    public function testVariantImageAssignByMediaId()
    {
        $data = $this->getSimpleArticleData();
        $data['mainDetail'] = $this->getSimpleVariantData();
        $configuratorSet = $this->getSimpleConfiguratorSet();
        $data['configuratorSet'] = $configuratorSet;
        $data['images'] = $this->getSimpleMedia(2);

        $article = $this->resourceArticle->create($data);

        $create = $this->getSimpleVariantData();
        $create['articleId'] = $article->getId();
        $create['configuratorOptions'] = $this->getVariantOptionsOfSet($configuratorSet);
        $create['images'] = $this->getSimpleMedia(1);

        /** @var $variant \Shopware\Models\Article\Detail */
        $variant = $this->resource->create($create);

        $this->assertCount(1, $variant->getImages());

        return $variant->getId();
    }

    /**
     * @depends testVariantImageAssignByMediaId
     *
     * @param $variantId
     *
     * @return int
     */
    public function testVariantImageReset($variantId)
    {
        $this->resource->setResultMode(Variant::HYDRATE_OBJECT);
        $variant = $this->resource->getOne($variantId);
        $this->assertTrue($variant->getImages()->count() > 0);

        $update = [
            'articleId' => $variant->getArticle()->getId(),
            'images' => [],
        ];

        $variant = $this->resource->update($variantId, $update);

        $this->assertCount(0, $variant->getImages());

        $article = $variant->getArticle();
        /** @var $image \Shopware\Models\Article\Image */
        foreach ($article->getImages() as $image) {
            $this->assertCount(0, $image->getMappings());
        }

        return $variant->getId();
    }

    /**
     * @depends testVariantImageReset
     *
     * @param $variantId
     */
    public function testVariantAddImage($variantId)
    {
        $this->resource->setResultMode(Variant::HYDRATE_OBJECT);
        $variant = $this->resource->getOne($variantId);
        $this->assertTrue($variant->getImages()->count() === 0);

        $update = [
            'articleId' => $variant->getArticle()->getId(),
            'images' => $this->getSimpleMedia(3),
        ];
        $variant = $this->resource->update($variantId, $update);
        $this->assertCount(3, $variant->getImages());

        $add = [
            'articleId' => $variant->getArticle()->getId(),
            '__options_images' => ['replace' => false],
            'images' => $this->getSimpleMedia(5, 20),
        ];
        $variant = $this->resource->update($variantId, $add);
        $this->assertCount(8, $variant->getImages());

        /** @var $image \Shopware\Models\Article\Image */
        foreach ($variant->getArticle()->getImages() as $image) {
            $this->assertCount(1, $image->getMappings(), 'No image mapping created!');

            /** @var $mapping \Shopware\Models\Article\Image\Mapping */
            $mapping = $image->getMappings()->current();
            $this->assertCount(
                $variant->getConfiguratorOptions()->count(),
                $mapping->getRules(),
                'Image mapping contains not enough rules. '
            );
        }
    }

    /**
     * @return int
     */
    public function testVariantImageCreateByLink()
    {
        $data = $this->getSimpleArticleData();
        $data['mainDetail'] = $this->getSimpleVariantData();
        $configuratorSet = $this->getSimpleConfiguratorSet();
        $data['configuratorSet'] = $configuratorSet;
        $article = $this->resourceArticle->create($data);
        $mediaService = Shopware()->Container()->get('shopware_media.media_service');

        $create = $this->getSimpleVariantData();
        $create['articleId'] = $article->getId();
        $create['configuratorOptions'] = $this->getVariantOptionsOfSet($configuratorSet);
        $create['images'] = [
            ['link' => 'data:image/png;base64,' . require(__DIR__ . '/fixtures/base64image.php')],
            ['link' => 'file://' . __DIR__ . '/fixtures/variant-image.png'],
        ];

        $this->resourceArticle->setResultMode(Variant::HYDRATE_OBJECT);
        $this->resource->setResultMode(Variant::HYDRATE_OBJECT);

        /** @var $variant \Shopware\Models\Article\Detail */
        $variant = $this->resource->create($create);
        $article = $this->resourceArticle->getOne($article->getId());

        $this->assertCount(2, $article->getImages());

        /** @var $image \Shopware\Models\Article\Image */
        foreach ($article->getImages() as $image) {
            $media = null;
            while ($media === null) {
                if ($image->getMedia()) {
                    $media = $image->getMedia();
                } elseif ($image->getParent()) {
                    $image = $image->getParent();
                } else {
                    break;
                }
            }

            $this->assertCount(4, $media->getThumbnails());
            foreach ($media->getThumbnails() as $thumbnail) {
                $this->assertTrue($mediaService->getFilesystem()->has(Shopware()->DocPath() . $thumbnail));
            }

            $this->assertCount(1, $image->getMappings(), 'No image mapping created!');

            /** @var $mapping \Shopware\Models\Article\Image\Mapping */
            $mapping = $image->getMappings()->current();
            $this->assertCount(
                $variant->getConfiguratorOptions()->count(),
                $mapping->getRules(),
                'Image mapping does not contain enough rules.'
            );
        }

        return $variant->getId();
    }

    public function testVariantDefaultPriceBehavior()
    {
        $data = $this->getSimpleArticleData();
        $data['mainDetail'] = $this->getSimpleVariantData();

        $configuratorSet = $this->getSimpleConfiguratorSet();
        $data['configuratorSet'] = $configuratorSet;

        $article = $this->resourceArticle->create($data);

        $create = $this->getSimpleVariantData();
        $create['articleId'] = $article->getId();
        $create['configuratorOptions'] = $this->getVariantOptionsOfSet($configuratorSet);

        $variant = $this->resource->create($create);

        $this->resource->setResultMode(2);
        $data = $this->resource->getOne($variant->getId());

        $this->assertEquals(400 / 1.19, $data['prices'][0]['price']);
    }

    public function testVariantGrossPrices()
    {
        $data = $this->getSimpleArticleData();
        $data['mainDetail'] = $this->getSimpleVariantData();

        $configuratorSet = $this->getSimpleConfiguratorSet();
        $data['configuratorSet'] = $configuratorSet;

        $article = $this->resourceArticle->create($data);

        $create = $this->getSimpleVariantData();
        $create['articleId'] = $article->getId();
        $create['configuratorOptions'] = $this->getVariantOptionsOfSet($configuratorSet);

        $variant = $this->resource->create($create);

        $this->resource->setResultMode(2);
        $data = $this->resource->getOne($variant->getId(), [
            'considerTaxInput' => true,
        ]);

        $this->assertEquals(400, $data['prices'][0]['price']);
    }

    public function testBatchModeShouldBeSuccessful()
    {
        $data = $this->getSimpleArticleData();
        $data['mainDetail'] = $this->getSimpleVariantData();
        $configuratorSet = $this->getSimpleConfiguratorSet();
        $data['configuratorSet'] = $configuratorSet;

        $article = $this->resourceArticle->create($data);
        $this->assertCount(0, $article->getDetails());

        // Create 5 new variants
        $batchData = [];
        for ($i = 0; $i < 5; ++$i) {
            $create = $this->getSimpleVariantData();
            $create['articleId'] = $article->getId();
            $create['configuratorOptions'] = $this->getVariantOptionsOfSet($configuratorSet);
            $batchData[] = $create;
        }

        // Update the price of the existing variant
        $existingVariant = $data['mainDetail'];
        $existingVariant['prices'] = [
            [
                'customerGroupKey' => 'EK',
                'from' => 1,
                'to' => '-',
                'price' => 473.99,
            ],
        ];
        $batchData[] = $existingVariant;

        // Run batch operations
        $this->resource->batch($batchData);

        // Check results
        $this->resourceArticle->setResultMode(Variant::HYDRATE_ARRAY);
        $id = $article->getId();
        $article = $this->resourceArticle->getOne($id);

        $this->assertCount(5, $article['details']);
        $this->assertEquals(398, round($article['mainDetail']['prices'][0]['price']));
    }

    public function testNewConfiguratorOptionForVariant()
    {
        $data = $this->getSimpleArticleData();
        $data['mainDetail'] = $this->getSimpleVariantData();
        $configuratorSet = $this->getSimpleConfiguratorSet(1, 2);
        $data['configuratorSet'] = $configuratorSet;

        $article = $this->resourceArticle->create($data);

        // Create 5 new variants
        $batchData = [];
        $names = [];
        for ($i = 0; $i < 5; ++$i) {
            $create = $this->getSimpleVariantData();
            $create['articleId'] = $article->getId();

            $options = $this->getVariantOptionsOfSet($configuratorSet);

            unset($options[0]['optionId']);
            $name = 'New-' . uniqid(rand());
            $names[] = $name;
            $options[0]['option'] = $name;
            $create['configuratorOptions'] = $options;

            $batchData[] = $create;
        }

        // Run batch operations
        $result = $this->resource->batch($batchData);

        $this->resource->setResultMode(Resource::HYDRATE_ARRAY);
        foreach ($result as $operation) {
            $this->assertTrue($operation['success']);

            $variant = $this->resource->getOne($operation['data']['id']);

            $this->assertCount(1, $variant['configuratorOptions']);

            $option = $variant['configuratorOptions'][0];

            $this->assertContains($option['name'], $names);
        }
    }

    public function testCreateConfiguratorOptionsWithPosition()
    {
        // required field name is missing
        $testData = [
            'name' => 'Testartikel',
            'taxId' => 1,
            'supplierId' => 2,
            'mainDetail' => [
                'number' => 'swTEST' . uniqid(rand()),
                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'from' => 1,
                        'to' => 20,
                        'price' => 500,
                    ],
                ],
            ],
            'configuratorSet' => [
                'name' => 'CreateOptionsWithPosition',
                'groups' => [
                    [
                        'name' => 'First group',
                        'options' => [
                            ['name' => 'group with 10', 'position' => 10],
                            ['name' => 'group with 5', 'position' => 5],
                        ],
                    ],
                    [
                        'name' => 'Second group',
                        'options' => [
                            ['name' => 'group with 30', 'position' => 30],
                            ['name' => 'group with 12', 'position' => 12],
                        ],
                    ],
                ],
            ],
        ];

        $article = $this->resourceArticle->create($testData);
        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertGreaterThan(0, $article->getId());

        $groups = $article->getConfiguratorSet()->getGroups();
        $this->assertCount(2, $groups);

        /** @var Group[] $groups */
        foreach ($groups as $group) {
            $this->assertCount(2, $group->getOptions());
            foreach ($group->getOptions() as $option) {
                switch ($option->getName()) {
                    case 'group with 10':
                        $this->assertEquals(10, $option->getPosition());
                        break;
                    case 'group with 5':
                        $this->assertEquals(5, $option->getPosition());
                        break;
                    case 'group with 30':
                        $this->assertEquals(30, $option->getPosition());
                        break;
                    case 'group with 12':
                        $this->assertEquals(12, $option->getPosition());
                        break;
                }
            }
        }
    }

    private function getVariantOptionsOfSet($configuratorSet)
    {
        $options = [];
        foreach ($configuratorSet['groups'] as $group) {
            $id = rand(0, count($group['options']) - 1);
            $option = $group['options'][$id];
            $options[] = [
                'optionId' => $option['id'],
                'groupId' => $group['id'],
            ];
        }

        return $options;
    }

    private function getSimpleMedia($limit = 5, $offset = 0)
    {
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select('media.id  as mediaId')
            ->from('Shopware\Models\Media\Media', 'media')
            ->where('media.albumId = -1')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $builder->getQuery()->getArrayResult();
    }

    private function getRandomId($table)
    {
        return Shopware()->Db()->fetchOne('SELECT id FROM ' . $table . ' LIMIT 1');
    }

    private function getSimpleVariantData()
    {
        return [
            'number' => 'swTEST' . uniqid(rand()),
            'inStock' => 100,
            'unitId' => 1,
            'prices' => [
                [
                    'customerGroupKey' => 'EK',
                    'from' => 1,
                    'to' => '-',
                    'price' => 400,
                ],
            ],
        ];
    }

    private function getSimpleArticleData()
    {
        return [
            'name' => 'Images - Test Artikel',
            'description' => 'Test description',
            'active' => true,
            'taxId' => 1,
            'supplierId' => 2,
        ];
    }

    private function getSimpleConfiguratorSet($groupLimit = 3, $optionLimit = 5)
    {
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(['groups.id'])
            ->from('Shopware\Models\Article\Configurator\Group', 'groups')
            ->setFirstResult(0)
            ->setMaxResults($groupLimit)
            ->orderBy('groups.position', 'ASC');

        $groups = $builder->getQuery()->getArrayResult();

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(['options.id'])
            ->from('Shopware\Models\Article\Configurator\Option', 'options')
            ->where('options.groupId = :groupId')
            ->setFirstResult(0)
            ->setMaxResults($optionLimit)
            ->orderBy('options.position', 'ASC');

        foreach ($groups as &$group) {
            $builder->setParameter('groupId', $group['id']);
            $group['options'] = $builder->getQuery()->getArrayResult();
        }

        return [
            'name' => 'Test-Set',
            'groups' => $groups,
        ];
    }
}
