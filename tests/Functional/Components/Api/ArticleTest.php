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
use Shopware\Models\Article\Image;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ArticleTest extends TestCase
{
    /**
     * @var Article
     */
    protected $resource;

    /**
     * @return Article
     */
    public function createResource()
    {
        return new Article();
    }

    public function testCreateShouldBeSuccessful()
    {
        $testData = [
            'name' => 'Testartikel',
            'description' => 'Test description',
            'descriptionLong' => 'Test descriptionLong',
            'active' => true,
            'pseudoSales' => 999,
            'highlight' => true,
            'keywords' => 'test, testarticle',
            'metaTitle' => 'this is a test title with umlauts äöüß',
            'filterGroupId' => 1,
            'propertyValues' => [
                [
                    'value' => 'grün',
                    'option' => [
                        'name' => 'Farbe',
                    ],
                ],
                [
                    'value' => 'testWert',
                    'option' => [
                        'name' => 'neueOption' . uniqid(rand()),
                    ],
                ],
            ],
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
                            ['name' => 'grün'],
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
                    // create a new unit
                    'unit' => [
                        'unit' => 'xyz',
                        'name' => 'newUnit',
                    ],
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
            'similar' => [
                ['id' => 5],
                ['id' => 6],
            ],
            'categories' => [
                ['id' => 15],
                ['id' => 10],
            ],
            'related' => [
                ['id' => 3, 'cross' => true],
                ['id' => 4],
            ],
            'links' => [
                ['name' => 'foobar', 'link' => 'http://example.org'],
                ['name' => 'Video', 'link' => 'http://example.org'],
            ],
        ];

        $article = $this->resource->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertGreaterThan(0, $article->getId());

        $this->assertEquals($article->getName(), $testData['name']);
        $this->assertEquals($article->getDescription(), $testData['description']);
        $this->assertEquals($article->getMetaTitle(), $testData['metaTitle']);

        $this->assertEquals($article->getDescriptionLong(), $testData['descriptionLong']);
        $this->assertEquals(
            $article->getMainDetail()->getAttribute()->getAttr1(),
            $testData['mainDetail']['attribute']['attr1']
        );
        $this->assertEquals(
            $article->getMainDetail()->getAttribute()->getAttr2(),
            $testData['mainDetail']['attribute']['attr2']
        );

        $propertyValues = $article->getPropertyValues()->getValues();
        $this->assertEquals(count($propertyValues), count($testData['propertyValues']));
        foreach ($propertyValues as $propertyValue) {
            $this->assertContains($propertyValue->getValue(), ['grün', 'testWert']);
        }

        $this->assertEquals($testData['taxId'], $article->getTax()->getId());

        $this->assertEquals(2, count($article->getCategories()));
        $this->assertEquals(2, count($article->getRelated()));
        $this->assertEquals(2, count($article->getSimilar()));
        $this->assertEquals(2, count($article->getLinks()));
        $this->assertEquals(2, count($article->getMainDetail()->getPrices()));
        foreach ($article->getMainDetail()->getPrices() as $price) {
            $this->assertGreaterThan(0, $price->getFrom());
        }
        foreach ($article->getDetails() as $variant) {
            foreach ($variant->getPrices() as $price) {
                $this->assertGreaterThan(0, $price->getFrom());
            }
        }

        return $article->getId();
    }

    public function testCreateWithNewUnitShouldBeSuccessful()
    {
        $testData = [
            'name' => 'Testarticle',
            'description' => 'testdescription',
            'descriptionLong' => 'Test descriptionLong',
            'active' => true,
            'pseudoSales' => 999,
            'highlight' => true,
            'keywords' => 'test, testarticle',
            'tax' => 19,
            'categories' => [
                ['id' => 15],
                ['id' => 10],
            ],
            'mainDetail' => [
                'number' => 'swTEST' . uniqid(rand()),
                // create new unit
                'unit' => [
                    'name' => 'newunit',
                    'unit' => 'newunit',
                ],
                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'price' => 999,
                    ],
                ],
            ],
        ];

        $article = $this->resource->create($testData);
        // change number for second article
        $testData['mainDetail']['number'] = 'swTEST' . uniqid(rand());
        $secondArticle = $this->resource->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertGreaterThan(0, $article->getId());

        $this->assertEquals($article->getName(), $testData['name']);
        $this->assertEquals($article->getDescription(), $testData['description']);
        $this->assertEquals($article->getMetaTitle(), $testData['metaTitle']);

        foreach ($article->getMainDetail()->getPrices() as $price) {
            $this->assertGreaterThan(0, $price->getFrom());
        }

        $this->assertInstanceOf('\Shopware\Models\Article\Unit', $article->getMainDetail()->getUnit());
        $this->assertGreaterThan(0, $article->getMainDetail()->getUnit()->getId());
        $this->assertEquals($article->getMainDetail()->getUnit()->getName(), $testData['mainDetail']['unit']['name']);
        $this->assertEquals($article->getMainDetail()->getUnit()->getUnit(), $testData['mainDetail']['unit']['unit']);

        $this->assertEquals($article->getMainDetail()->getUnit()->getId(), $secondArticle->getMainDetail()->getUnit()->getId());

        return $article->getId();
    }

    /**
     * Test that creating an article with images generates thumbnails
     *
     * @return int Article Id
     */
    public function testCreateWithImageShouldCreateThumbnails()
    {
        $testData = [
            'name' => 'Test article with images',
            'description' => 'Test description',
            'active' => true,
            'filterGroupId' => 1,
            'propertyValues' => [
                [
                    'value' => 'grün',
                    'option' => [
                        'name' => 'Farbe',
                    ],
                ],
                [
                    'value' => 'testWert',
                    'option' => [
                        'name' => 'neueOption' . uniqid(rand()),
                    ],
                ],
            ],
            'images' => [
                [
                    'link' => 'file://' . __DIR__ . '/fixtures/test-bild.jpg',
                ],
                [
                    'link' => 'data:image/png;base64,' . require(__DIR__ . '/fixtures/base64image.php'),
                ],
                [
                    'link' => 'file://' . __DIR__ . '/fixtures/variant-image.png',
                ],
            ],
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
                            ['name' => 'grün'],
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
                    // create a new unit
                    'unit' => [
                        'unit' => 'xyz',
                        'name' => 'newUnit',
                    ],
                    'attribute' => [
                        'attr3' => 'Freitext3',
                        'attr4' => 'Freitext4',
                    ],
                    'images' => [
                        [
                            'link' => 'file://' . __DIR__ . '/fixtures/test-bild.jpg',
                        ],
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
            ],
            'taxId' => 1,
            'supplierId' => 2,
        ];

        $article = $this->resource->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertGreaterThan(0, $article->getId());
        $this->assertCount(4, $article->getImages());

        $mediaService = Shopware()->Container()->get('shopware_media.media_service');

        foreach ($article->getImages() as $image) {
            $this->assertCount(4, $image->getMedia()->getThumbnails());
            foreach ($image->getMedia()->getThumbnails() as $thumbnail) {
                $this->assertTrue($mediaService->getFilesystem()->has($thumbnail));
            }
        }
        foreach ($article->getMainDetail()->getPrices() as $price) {
            $this->assertGreaterThan(0, $price->getFrom());
        }
        foreach ($article->getDetails() as $variant) {
            foreach ($variant->getPrices() as $price) {
                $this->assertGreaterThan(0, $price->getFrom());
            }
        }

        return $article->getId();
    }

    /**
     * @depends testCreateWithImageShouldCreateThumbnails
     *
     * @param int $id
     */
    public function testFlipArticleMainVariantShouldBeSuccessful($id)
    {
        $originalArticle = $this->resource->getOne($id);
        $mainVariantNumber = $originalArticle['mainDetailId'];

        $testData = [
            'mainDetail' => [
                'number' => $mainVariantNumber,
                'inStock' => 15,
                'unitId' => 1,
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
            'variants' => [
                [
                    'number' => $mainVariantNumber,
                    'inStock' => 15,
                    'unitId' => 1,
                    'isMain' => true,
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
                ],
            ],
        ];

        $article = $this->resource->update($id, $testData);

        $this->assertEquals($mainVariantNumber, $article->getMainDetail()->getNumber());
    }

    /**
     * Test that updating an Article with images generates thumbnails
     *
     * @depends testCreateWithImageShouldCreateThumbnails
     *
     * @param int $id
     *
     * @return int
     */
    public function testUpdateWithImageShouldCreateThumbnails($id)
    {
        $testData = [
            'images' => [
                [
                    'link' => 'https://cdn.shopware.de/github/readme_screenshot.png',
                ],
            ],
        ];

        $article = $this->resource->update($id, $testData);
        $mediaService = Shopware()->Container()->get('shopware_media.media_service');

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertGreaterThan(0, $article->getId());

        $this->assertCount(5, $article->getImages());
        foreach ($article->getImages() as $image) {
            $this->assertCount(4, $image->getMedia()->getThumbnails());
            foreach ($image->getMedia()->getThumbnails() as $thumbnail) {
                $this->assertTrue($mediaService->getFilesystem()->has($thumbnail));
            }
        }

        // Cleanup test data
        $this->resource->delete($id);
    }

    /**
     * Tests the thumbnail generation and their proportional sizes
     *
     * @return int
     */
    public function testCreateWithImageShouldCreateThumbnailsWithRightProportions()
    {
        $testData = [
            'name' => 'Test article with images and right proportions',
            'description' => 'Test description',
            'active' => true,
            'filterGroupId' => 1,
            'propertyValues' => [
                [
                    'value' => 'grün',
                    'option' => [
                        'name' => 'Farbe',
                    ],
                ],
                [
                    'value' => 'testWert',
                    'option' => [
                        'name' => 'neueOption' . uniqid(rand()),
                    ],
                ],
            ],
            'images' => [
                [
                    'link' => 'file://' . __DIR__ . '/fixtures/test-bild.jpg',
                ],
            ],
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
                            ['name' => 'grün'],
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
                    // create a new unit
                    'unit' => [
                        'unit' => 'xyz',
                        'name' => 'newUnit',
                    ],
                    'attribute' => [
                        'attr3' => 'Freitext3',
                        'attr4' => 'Freitext4',
                    ],
                    'images' => [
                        [
                            'link' => 'file://' . __DIR__ . '/fixtures/test-bild.jpg',
                        ],
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
            ],
            'taxId' => 1,
            'supplierId' => 2,
        ];

        $article = $this->resource->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertGreaterThan(0, $article->getId());
        $this->assertCount(2, $article->getImages());

        $proportionalSizes = [
            '200x200',
            '600x600',
            '1280x1280',
            '140x140',
        ];

        $mediaService = Shopware()->Container()->get('shopware_media.media_service');

        foreach ($article->getImages() as $image) {
            $thumbnails = $image->getMedia()->getThumbnails();
            $this->assertCount(4, $thumbnails);
            $thumbnails = array_values($thumbnails);

            foreach ($thumbnails as $key => $thumbnail) {
                $this->assertTrue($mediaService->getFilesystem()->has($thumbnail));

                $image = imagecreatefromstring($mediaService->getFilesystem()->read($thumbnail));
                $width = imagesx($image);
                $height = imagesy($image);

                $this->assertSame($proportionalSizes[$key], $width . 'x' . $height);
            }
        }

        $this->resource->delete($article->getId());
    }

    /**
     * Test creating an article with new configurator set and multiple variants
     * SW-7925
     *
     * @return int
     */
    public function testCreateWithVariantsAndNewConfiguratorSetShouldBeSuccessful()
    {
        $testData = [
            'name' => 'Test article',
            'description' => 'Test description',
            'descriptionLong' => 'Long test description',
            'active' => true,
            'pseudoSales' => 999,
            'highlight' => true,
            'keywords' => 'test, testarticle',
            'metaTitle' => 'this is a test title with umlauts äöüß',
            'filterGroupId' => 1,
            'propertyValues' => [
                [
                    'value' => 'grün',
                    'option' => [
                        'name' => 'Farbe',
                    ],
                ],
                [
                    'value' => 'testWert',
                    'option' => [
                        'name' => 'neueOption' . uniqid(rand()),
                    ],
                ],
            ],
            'mainDetail' => [
                'number' => 'swConfigSetMainTest' . uniqid(rand()),
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
                'name' => 'NewConfigSet',
                'groups' => [
                    [
                        'name' => 'Group1',
                        'options' => [
                            ['name' => 'Opt11'],
                            ['name' => 'Opt12'],
                        ],
                    ],
                    [
                        'name' => 'Group2',
                        'options' => [
                            ['name' => 'Opt21'],
                            ['name' => 'Opt22'],
                            ['name' => 'Opt23'],
                            ['name' => 'Opt24'],
                            ['name' => 'Opt25'],
                            ['name' => 'Opt26'],
                            ['name' => 'Opt27'],
                        ],
                    ],
                ],
            ],
            'variants' => [
                [
                    'number' => 'swConfigSetMainTest.variant.' . uniqid(rand()),
                    'inStock' => 17,
                    // create a new unit
                    'unit' => [
                        'unit' => 'xyz',
                        'name' => 'newUnit',
                    ],
                    'attribute' => [
                        'attr3' => 'Freitext3',
                        'attr4' => 'Freitext4',
                    ],
                    'configuratorOptions' => [
                        [
                            'option' => 'Opt11',
                            'group' => 'Group1',
                        ],
                        [
                            'option' => 'Opt23',
                            'group' => 'Group2',
                        ],
                        [
                            'option' => 'Opt24',
                            'group' => 'Group2',
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
                    'number' => 'swConfigSetMainTest.variant.' . uniqid(rand()),
                    'inStock' => 18,
                    // create another new unit
                    'unit' => [
                        'unit' => 'xyz',
                        'name' => 'newUnit',
                    ],
                    'attribute' => [
                        'attr3' => 'Freitext3',
                        'attr4' => 'Freitext4',
                    ],
                    'configuratorOptions' => [
                        [
                            'option' => 'Opt12',
                            'group' => 'Group1',
                        ],
                        [
                            'option' => 'Opt27',
                            'group' => 'Group2',
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
            'categories' => [
                ['id' => 15],
                ['id' => 10],
            ],
            'links' => [
                ['name' => 'foobar', 'link' => 'http://example.org'],
                ['name' => 'Video', 'link' => 'http://example.org'],
            ],
        ];

        $article = $this->resource->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertGreaterThan(0, $article->getId());

        $this->assertEquals($article->getName(), $testData['name']);
        $this->assertEquals($article->getDescription(), $testData['description']);
        $this->assertEquals($article->getMetaTitle(), $testData['metaTitle']);

        $this->assertEquals($article->getDescriptionLong(), $testData['descriptionLong']);
        $this->assertEquals(
            $article->getMainDetail()->getAttribute()->getAttr1(),
            $testData['mainDetail']['attribute']['attr1']
        );
        $this->assertEquals(
            $article->getMainDetail()->getAttribute()->getAttr2(),
            $testData['mainDetail']['attribute']['attr2']
        );

        $propertyValues = $article->getPropertyValues()->getValues();
        $this->assertEquals(count($propertyValues), count($testData['propertyValues']));
        foreach ($propertyValues as $propertyValue) {
            $this->assertContains($propertyValue->getValue(), ['grün', 'testWert']);
        }

        $this->assertEquals($testData['taxId'], $article->getTax()->getId());

        $this->assertEquals(2, count($article->getCategories()));
        $this->assertEquals(0, count($article->getRelated()));
        $this->assertEquals(0, count($article->getSimilar()));
        $this->assertEquals(2, count($article->getLinks()));
        $this->assertEquals(2, count($article->getMainDetail()->getPrices()));

        $groups = Shopware()->Models()->getRepository('Shopware\Models\Article\Configurator\Group')->findBy(
                ['name' => ['Group1', 'Group2']]
            );

        foreach ($groups as $group) {
            Shopware()->Models()->remove($group);
        }

        $this->resource->delete($article->getId());
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneByNumberShouldBeSuccessful($id)
    {
        $this->resource->setResultMode(Article::HYDRATE_OBJECT);
        $article = $this->resource->getOne($id);
        $number = $article->getMainDetail()->getNumber();

        $article = $this->resource->getOneByNumber($number);
        $this->assertEquals($id, $article->getId());
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneShouldBeSuccessful($id)
    {
        $article = $this->resource->getOne($id);
        $this->assertGreaterThan(0, $article['id']);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneShouldBeAbleToReturnObject($id)
    {
        $this->resource->setResultMode(Article::HYDRATE_OBJECT);
        $article = $this->resource->getOne($id);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertGreaterThan(0, $article->getId());
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
     * @expectedException \Shopware\Components\Api\Exception\ValidationException
     */
    public function testCreateWithInvalidDataShouldThrowValidationException()
    {
        // required field name is missing
        $testData = [
            'description' => 'Update description',
            'descriptionLong' => 'Update descriptionLong',
        ];

        $this->resource->create($testData);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testUpdateByNumberShouldBeSuccessful($id)
    {
        $this->resource->setResultMode(Article::HYDRATE_OBJECT);
        $article = $this->resource->getOne($id);
        $number = $article->getMainDetail()->getNumber();

        $testData = [
            'description' => 'Update description',
            'descriptionLong' => 'Update descriptionLong',
            // update supplier id
            'supplierId' => 3,
            // categories should be replaced
            'categories' => [
                ['id' => 16],
            ],
            'filterGroupId' => 1,
            // values should be replaced
            'propertyValues' => [],
            // related is not included, therefore it stays untouched

            // similar is set to empty array, therefore it should be cleared
            'similar' => [],
        ];

        $article = $this->resource->updateByNumber($number, $testData);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertEquals($id, $article->getId());
        $this->assertEquals($article->getDescription(), $testData['description']);
        $this->assertEquals($article->getDescriptionLong(), $testData['descriptionLong']);

        $this->assertEquals($testData['supplierId'], $article->getSupplier()->getId());

        $propertyValues = $article->getPropertyValues()->getValues();
        $this->assertEquals(count($propertyValues), count($testData['propertyValues']));

        // Categories should be updated
        $this->assertEquals(1, count($article->getCategories()));

        // Related should be untouched
        $this->assertEquals(2, count($article->getRelated()));

        // Similar should be removed
        $this->assertEquals(0, count($article->getSimilar()));

        return $number;
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testUpdateShouldBeSuccessful($id)
    {
        $testData = [
            'description' => 'Update description',
            'descriptionLong' => 'Update descriptionLong',
            // update supplier id
            'supplierId' => 3,
            // categories should be replaced
            'categories' => [
                ['id' => 16],
            ],
            'filterGroupId' => 1,
            // values should be replaced
            'propertyValues' => [],
            // related is not included, therefore it stays untouched

            // similar is set to empty array, therefore it should be cleared
            'similar' => [],
        ];

        $article = $this->resource->update($id, $testData);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertEquals($id, $article->getId());
        $this->assertEquals($article->getDescription(), $testData['description']);
        $this->assertEquals($article->getDescriptionLong(), $testData['descriptionLong']);

        $this->assertEquals($testData['supplierId'], $article->getSupplier()->getId());

        $propertyValues = $article->getPropertyValues()->getValues();
        $this->assertEquals(count($propertyValues), count($testData['propertyValues']));

        // Categories should be updated
        $this->assertEquals(1, count($article->getCategories()));

        // Related should be untouched
        $this->assertEquals(2, count($article->getRelated()));

        // Similar should be removed
        $this->assertEquals(0, count($article->getSimilar()));

        return $id;
    }

    /**
     * @depends testCreateShouldBeSuccessful
     * @expectedException \Shopware\Components\Api\Exception\ValidationException
     */
    public function testUpdateWithInvalidDataShouldThrowValidationException($id)
    {
        // required field name is blank
        $testData = [
            'name' => ' ',
            'description' => 'Update description',
            'descriptionLong' => 'Update descriptionLong',
        ];

        $this->resource->update($id, $testData);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testUpdateWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->update(9999999, []);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testUpdateWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->update('', []);
    }

    /**
     * @depends testUpdateShouldBeSuccessful
     */
    public function testDeleteShouldBeSuccessful($id)
    {
        $article = $this->resource->delete($id);

        $this->assertInstanceOf('\Shopware\Models\Article\Article', $article);
        $this->assertEquals(null, $article->getId());
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

    /**
     * Test case to add a new article image over a media id.
     */
    public function testAddArticleMediaOverMediaId()
    {
        $this->resource->update(
            2,
            [
                'images' => [
                    [
                        'articleId' => 2,
                        'mediaId' => 25,
                        'main' => 0,
                        'position' => 10000,
                    ],
                ],
            ]
        );
        $article = $this->resource->getOne(2);

        $image = array_pop($article['images']);
        $this->assertEquals($image['mediaId'], 25);
    }

    public function testUpdateToVariantArticle()
    {
        try {
            $id = $this->resource->getIdFromNumber('turn');
            if (!empty($id)) {
                $this->resource->delete($id);
            }
        } catch (\Exception $e) {
        }

        $article = $this->resource->create(
            [
                'name' => 'Turnschuhe',
                'active' => true,
                'tax' => 19,
                'supplier' => 'Turnschuhe Inc.',
                'categories' => [
                    ['id' => 15],
                ],
                'mainDetail' => [
                    'number' => 'turn',
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
            ]
        );

        $updateArticle = [
            'configuratorSet' => [
                'groups' => [
                    [
                        'name' => 'Größe',
                        'options' => [
                            ['name' => 'S'],
                            ['name' => 'M'],
                        ],
                    ],
                    [
                        'name' => 'Farbe',
                        'options' => [
                            ['name' => 'grün'],
                            ['name' => 'blau'],
                        ],
                    ],
                ],
            ],
            'taxId' => 1,
            'variants' => [
                [
                    'isMain' => true,
                    'number' => 'turn',
                    'inStock' => 15,
                    'addtionnaltext' => 'S / grün',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'S'],
                        ['group' => 'Farbe', 'option' => 'grün'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 1999,
                        ],
                    ],
                ],
                [
                    'isMain' => false,
                    'number' => 'turn.1',
                    'inStock' => 15,
                    'addtionnaltext' => 'S / blau',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'S'],
                        ['group' => 'Farbe', 'option' => 'blau'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
                [
                    'isMain' => false,
                    'number' => 'turn.2',
                    'inStock' => 15,
                    'addtionnaltext' => 'M / grün',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'M'],
                        ['group' => 'Farbe', 'option' => 'grün'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
                [
                    'isMain' => false,
                    'number' => 'turn.3',
                    'inStock' => 15,
                    'addtionnaltext' => 'M / blau',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'M'],
                        ['group' => 'Farbe', 'option' => 'blau'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
            ],
        ];
        /** @var $article \Shopware\Models\Article\Article */
        $updated = $this->resource->update($article->getId(), $updateArticle);
        $this->assertEquals($updated->getName(), 'Turnschuhe', "Article name don't match");

        /** @var $variant \Shopware\Models\Article\Detail */
        foreach ($updated->getDetails() as $variant) {
            $this->assertTrue(
                in_array(
                    $variant->getNumber(),
                    ['turn', 'turn.1', 'turn.2', 'turn.3'],
                    'Variant number dont match'
                )
            );

            $this->assertCount(2, $variant->getConfiguratorOptions(), 'Configurator option count dont match');

            /** @var $option \Shopware\Models\Article\Configurator\Option */
            foreach ($variant->getConfiguratorOptions() as $option) {
                $this->assertTrue(in_array($option->getName(), ['M', 'S', 'blau', 'grün']));
            }
        }

        try {
            if (!empty($id)) {
                $this->resource->delete($id);
            }
        } catch (\Exception $e) {
        }
    }

    public function testUpdateToConfiguratorSetPosition()
    {
        try {
            $id = $this->resource->getIdFromNumber('turn');
            if (!empty($id)) {
                $this->resource->delete($id);
            }
        } catch (\Exception $e) {
        }

        $article = $this->resource->create(
            [
                'name' => 'Turnschuhe',
                'active' => true,
                'tax' => 19,
                'supplier' => 'Turnschuhe Inc.',
                'categories' => [
                    ['id' => 15],
                ],
                'mainDetail' => [
                    'number' => 'turn',
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
            ]
        );

        $updateArticle = [
            'configuratorSet' => [
                'groups' => [
                    [
                        'name' => 'Größe',
                        'options' => [
                            ['name' => 'S', 'position' => 123],
                            ['name' => 'M', 'position' => 4],
                        ],
                    ],
                    [
                        'name' => 'Farbe',
                        'options' => [
                            ['name' => 'grün', 'position' => 99],
                            ['name' => 'blau', 'position' => 11],
                        ],
                    ],
                ],
            ],
            'taxId' => 1,
            'variants' => [
                [
                    'isMain' => true,
                    'number' => 'turn',
                    'inStock' => 15,
                    'addtionnaltext' => 'S / grün',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'S'],
                        ['group' => 'Farbe', 'option' => 'grün'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 1999,
                        ],
                    ],
                ],
                [
                    'isMain' => false,
                    'number' => 'turn.1',
                    'inStock' => 15,
                    'addtionnaltext' => 'S / blau',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'S'],
                        ['group' => 'Farbe', 'option' => 'blau'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
                [
                    'isMain' => false,
                    'number' => 'turn.2',
                    'inStock' => 15,
                    'addtionnaltext' => 'M / grün',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'M'],
                        ['group' => 'Farbe', 'option' => 'grün'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
                [
                    'isMain' => false,
                    'number' => 'turn.3',
                    'inStock' => 15,
                    'addtionnaltext' => 'M / blau',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'M'],
                        ['group' => 'Farbe', 'option' => 'blau'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
            ],
        ];
        /** @var $article \Shopware\Models\Article\Article */
        $updated = $this->resource->update($article->getId(), $updateArticle);
        $this->assertEquals($updated->getName(), 'Turnschuhe', "Article name doesn't match");

        /** @var $variant \Shopware\Models\Article\Detail */
        foreach ($updated->getDetails() as $variant) {
            $this->assertTrue(
                in_array(
                    $variant->getNumber(),
                    ['turn', 'turn.1', 'turn.2', 'turn.3'],
                    'Variant number dont match'
                )
            );

            /** @var $option \Shopware\Models\Article\Configurator\Option */
            foreach ($variant->getConfiguratorOptions() as $option) {
                $this->assertTrue(in_array($option->getName(), ['M', 'S', 'blau', 'grün']));

                switch ($option->getName()) {
                    case 'M':
                        $this->assertEquals(4, $option->getPosition());
                        break;
                    case 'S':
                        $this->assertEquals(123, $option->getPosition());
                        break;
                    case 'blau':
                        $this->assertEquals(11, $option->getPosition());
                        break;
                    case 'grün':
                        $this->assertEquals(99, $option->getPosition());
                        break;

                    default:
                        $this->assertTrue(false, 'There is an unknown variant.');
                }
            }
        }

        try {
            if (!empty($id)) {
                $this->resource->delete($id);
            }
        } catch (\Exception $e) {
        }
    }

    public function testUpdateToConfiguratorSetType()
    {
        try {
            $id = $this->resource->getIdFromNumber('turn');
            if (!empty($id)) {
                $this->resource->delete($id);
            }
        } catch (\Exception $e) {
        }

        $article = $this->resource->create(
            [
                'name' => 'Turnschuhe',
                'active' => true,
                'tax' => 19,
                'supplier' => 'Turnschuhe Inc.',
                'categories' => [
                    ['id' => 15],
                ],
                'mainDetail' => [
                    'number' => 'turn',
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
            ]
        );

        $updateArticle = [
            'configuratorSet' => [
                'type' => 2,
                'groups' => [
                    [
                        'name' => 'Größe',
                        'options' => [
                            ['name' => 'S', 'position' => 123],
                            ['name' => 'M', 'position' => 4],
                        ],
                    ],
                    [
                        'name' => 'Farbe',
                        'options' => [
                            ['name' => 'grün', 'position' => 99],
                            ['name' => 'blau', 'position' => 11],
                        ],
                    ],
                ],
            ],
            'taxId' => 1,
            'variants' => [
                [
                    'isMain' => true,
                    'number' => 'turn',
                    'inStock' => 15,
                    'addtionnaltext' => 'S / grün',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'S'],
                        ['group' => 'Farbe', 'option' => 'grün'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 1999,
                        ],
                    ],
                ],
                [
                    'isMain' => false,
                    'number' => 'turn.1',
                    'inStock' => 15,
                    'addtionnaltext' => 'S / blau',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'S'],
                        ['group' => 'Farbe', 'option' => 'blau'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
                [
                    'isMain' => false,
                    'number' => 'turn.2',
                    'inStock' => 15,
                    'addtionnaltext' => 'M / grün',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'M'],
                        ['group' => 'Farbe', 'option' => 'grün'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
                [
                    'isMain' => false,
                    'number' => 'turn.3',
                    'inStock' => 15,
                    'addtionnaltext' => 'M / blau',
                    'configuratorOptions' => [
                        ['group' => 'Größe', 'option' => 'M'],
                        ['group' => 'Farbe', 'option' => 'blau'],
                    ],
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 999,
                        ],
                    ],
                ],
            ],
        ];
        /** @var $article \Shopware\Models\Article\Article */
        $updated = $this->resource->update($article->getId(), $updateArticle);
        $this->assertEquals($updated->getConfiguratorSet()->getType(), 2, "ConfiguratorSet.Type doesn't match");

        try {
            if (!empty($id)) {
                $this->resource->delete($id);
            }
        } catch (\Exception $e) {
        }
    }

    public function testCreateUseConfiguratorId()
    {
        $configurator = $this->getSimpleConfiguratorSet(2, 5);
        $variantOptions = $this->getVariantOptionsOfSet($configurator);
        $variantNumber = 'swVariant' . uniqid(rand());

        $testData = [
            'name' => 'Testartikel',
            'description' => 'Test description',
            'descriptionLong' => 'Test descriptionLong',
            'active' => true,
            'taxId' => 1,
            'supplierId' => 1,
            'mainDetail' => [
                'number' => 'swTEST' . uniqid(rand()),
                'inStock' => 15,
                'unitId' => 1,
                'prices' => [
                    ['customerGroupKey' => 'EK', 'from' => 1, 'to' => '-', 'price' => 400],
                ],
            ],
            'variants' => [
                [
                    'number' => $variantNumber,
                    'inStock' => 15,
                    'unitId' => 1,
                    'prices' => [
                        ['customerGroupKey' => 'EK', 'from' => 1, 'to' => '-', 'price' => 400],
                    ],
                    'configuratorOptions' => $variantOptions,
                ],
            ],
            'configuratorSet' => $configurator,
        ];

        $article = $this->resource->create($testData);

        $this->resource->setResultMode(Article::HYDRATE_ARRAY);
        $data = $this->resource->getOne($article->getId());

        $this->assertCount(2, $data['details'][0]['configuratorOptions']);

        return $variantNumber;
    }

    /**
     * @depends testCreateUseConfiguratorId
     */
    public function testUpdateUseConfiguratorIds($variantNumber)
    {
        $configurator = $this->getSimpleConfiguratorSet(2, 5);
        $variantOptions = $this->getVariantOptionsOfSet($configurator);

        $id = Shopware()->Db()->fetchOne(
            'SELECT articleID FROM s_articles_details WHERE ordernumber = ?',
            [$variantNumber]
        );

        $data = [
            'variants' => [
                [
                    'number' => $variantNumber,
                    'configuratorOptions' => $variantOptions,
                ],
            ],
        ];

        $this->resource->update($id, $data);

        $data = $this->resource->getOne($id);
        $this->assertCount(2, $data['details'][0]['configuratorOptions']);
    }

    public function testCreateWithMainImages()
    {
        $this->resource->setResultMode(
            Resource::HYDRATE_OBJECT
        );

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(
            [
                'media.id as mediaId',
                '2 as main',
            ]
        )->from('Shopware\Models\Media\Media', 'media')->addOrderBy('media.id', 'ASC')->setFirstResult(
                5
            )->setMaxResults(4);

        /**
         * Get random images.
         * Only want to check if the main flag will be used.
         */
        $images = $builder->getQuery()->getArrayResult();
        $images[2]['main'] = 1;
        $expectedMainId = $images[2]['mediaId'];

        $data = $this->getSimpleTestData();
        $data['images'] = $images;
        $article = $this->resource->create($data);

        $this->assertCount(4, $article->getImages());

        $mainFlagExists = false;

        /** @var $image \Shopware\Models\Article\Image */
        foreach ($article->getImages() as $image) {
            if ($image->getMain() === 1) {
                $mainFlagExists = true;
                $this->assertEquals($expectedMainId, $image->getMedia()->getId());
            }
        }
        $this->assertTrue($mainFlagExists);

        return $article->getId();
    }

    /**
     * @depends testCreateWithMainImages
     */
    public function testUpdateWithSingleMainImage($articleId)
    {
        $this->resource->setResultMode(
            Resource::HYDRATE_ARRAY
        );
        $article = $this->resource->getOne($articleId);

        $updateImages = [];
        $newId = null;
        foreach ($article['images'] as $image) {
            if ($image['main'] !== 1) {
                $updateImages['images'][] = [
                    'id' => $image['id'],
                    'main' => 1,
                ];
                $newId = $image['id'];
                break;
            }
        }
        $article = $this->resource->update($articleId, $updateImages);

        $this->assertCount(4, $article->getImages());

        $hasMain = false;
        foreach ($article->getImages() as $image) {
            if ($image->getMain() === 1) {
                $hasMain = true;
                $this->assertEquals($image->getId(), $newId);
            }
        }
        $this->assertTrue($hasMain);

        return $article->getId();
    }

    /**
     * @depends testUpdateWithSingleMainImage
     */
    public function testUpdateWithMainImage($articleId)
    {
        $this->resource->getManager()->clear();

        $this->resource->setResultMode(
            Resource::HYDRATE_ARRAY
        );
        $article = $this->resource->getOne($articleId);

        $updateImages = [];
        $lastMainId = null;

        foreach ($article['images'] as $image) {
            $newImageData = [
                'id' => $image['id'],
                'main' => $image['main'],
            ];

            if ($image['main'] == 1) {
                $lastMainId = $image['id'];
                $newImageData['main'] = 2;
            }

            $updateImages['images'][] = $newImageData;
        }

        $newMainId = null;
        foreach ($updateImages['images'] as &$image) {
            if ($image['id'] !== $lastMainId) {
                $image['main'] = 1;
                $newMainId = $image['id'];
                break;
            }
        }
        $article = $this->resource->update($articleId, $updateImages);
        $this->assertCount(4, $article->getImages());

        $hasMain = false;
        /** @var $image \Shopware\Models\Article\Image */
        foreach ($article->getImages() as $image) {
            if ($image->getMain() === 1) {
                $hasMain = true;
                $this->assertEquals($newMainId, $image->getId());
            }
        }
        $this->assertTrue($hasMain);
    }

    /**
     * This unit test, tests if the attribute fields are translatable.
     */
    public function testCreateTranslation()
    {
        $data = $this->getSimpleTestData();

        $definedTranslation = [
            [
                'shopId' => 2,
                'name' => 'English-Name',
                'description' => 'English-Description',
                'descriptionLong' => 'English-DescriptionLong',
                'keywords' => 'English-Keywords',
                'packUnit' => 'English-PackUnit',
            ],
        ];

        for ($i = 1; $i <= 20; ++$i) {
            $definedTranslation[0]['__attribute_attr' . $i] = 'English-Attr' . $i;
        }

        $data['translations'] = $definedTranslation;

        $article = $this->resource->create($data);
        $newData = $this->resource->getOne($article->getId());

        $savedTranslation = $newData['translations'][2];
        $definedTranslation = $definedTranslation[0];

        $this->assertEquals($definedTranslation['name'], $savedTranslation['name']);
        $this->assertEquals($definedTranslation['description'], $savedTranslation['description']);
        $this->assertEquals($definedTranslation['descriptionLong'], $savedTranslation['descriptionLong']);
        $this->assertEquals($definedTranslation['keywords'], $savedTranslation['keywords']);
        $this->assertEquals($definedTranslation['packUnit'], $savedTranslation['packUnit']);

        for ($i = 1; $i <= 20; ++$i) {
            $attr = '__attribute_attr' . $i;
            $this->assertEquals($definedTranslation[$attr], $savedTranslation[$attr]);
        }
    }

    public function testBase64ImageUpload()
    {
        $data = $this->getSimpleTestData();

        $data['images'] = [
            [
                'link' => 'data:image/png;base64,' . require(__DIR__ . '/fixtures/base64image.php'),
            ],
        ];

        $model = $this->resource->create($data);
        $this->resource->setResultMode(
            Resource::HYDRATE_ARRAY
        );
        $article = $this->resource->getOne($model->getId());

        $mediaService = Shopware()->Container()->get('shopware_media.media_service');

        $this->assertCount(count($data['images']), $article['images']);
        foreach ($article['images'] as $image) {
            $key = 'media/image/' . $image['path'] . '.' . $image['extension'];
            $this->assertTrue($mediaService->getFilesystem()->has($key));

            $imageContent = $mediaService->getFilesystem()->read($key);

            $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $imageContent);
            $this->assertEquals('image/png', $mimeType);
        }
    }

    public function testImageReplacement()
    {
        $data = $this->getSimpleTestData();
        $data['images'] = $this->getImagesForNewArticle();
        $article = $this->resource->create($data);

        $createdIds = Shopware()->Db()->fetchCol(
            'SELECT id FROM s_articles_img WHERE articleID = :articleId',
            [
                ':articleId' => $article->getId(),
            ]
        );

        $data = [
            '__options_images' => ['replace' => true],
            'images' => $this->getImagesForNewArticle(100),
        ];

        $this->resource->update($article->getId(), $data);

        $updateIds = Shopware()->Db()->fetchCol(
            'SELECT id FROM s_articles_img WHERE articleID = :articleId',
            [
                ':articleId' => $article->getId(),
            ]
        );

        foreach ($updateIds as $id) {
            $this->assertNotContains($id, $createdIds);
        }
        $this->assertCount(5, $updateIds);
    }

    public function testImageReplacementMerge()
    {
        $data = $this->getSimpleTestData();
        $data['images'] = $this->getImagesForNewArticle();
        $article = $this->resource->create($data);

        $data = [
            '__options_images' => ['replace' => false],
            'images' => $this->getImagesForNewArticle(40),
        ];

        $this->resource->update($article->getId(), $data);

        $updateIds = Shopware()->Db()->fetchCol(
            'SELECT id FROM s_articles_img WHERE articleID = :articleId',
            [
                ':articleId' => $article->getId(),
            ]
        );

        $this->assertCount(10, $updateIds);
    }

    public function testImageReplacementWithoutOption()
    {
        $data = $this->getSimpleTestData();
        $data['images'] = $this->getImagesForNewArticle();
        $article = $this->resource->create($data);

        $data = [
            'images' => $this->getImagesForNewArticle(40),
        ];

        $this->resource->update($article->getId(), $data);

        $updateIds = Shopware()->Db()->fetchCol(
            'SELECT id FROM s_articles_img WHERE articleID = :articleId',
            [
                ':articleId' => $article->getId(),
            ]
        );

        $this->assertCount(10, $updateIds);
    }

    public function testImageAttributes()
    {
        $data = $this->getSimpleTestData();
        $images = $this->getImagesForNewArticle();
        foreach ($images as &$image) {
            $image['attribute'] = [
                'attribute1' => 'attr1',
            ];
        }
        $data['images'] = $images;
        $article = $this->resource->create($data);

        /** @var $image \Shopware\Models\Article\Image */
        foreach ($article->getImages() as $image) {
            $this->assertInstanceOf('\Shopware\Models\Attribute\ArticleImage', $image->getAttribute());
            $this->assertEquals('attr1', $image->getAttribute()->getAttribute1());
            $this->assertEquals(null, $image->getAttribute()->getAttribute2());
            $this->assertEquals(null, $image->getAttribute()->getAttribute3());
        }
    }

    public function testCreateWithDuplicateProperties()
    {
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(['values', 'option'])->from('Shopware\Models\Property\Value', 'values')->innerJoin(
                'values.option',
                'option'
            )->setFirstResult(0)->setMaxResults(20);
        $databaseValues = $builder->getQuery()->getArrayResult();
        $properties = [];
        foreach ($databaseValues as $value) {
            $valueIds[] = $value['id'];
            $optionIds[] = $value['option']['id'];
            $properties[] = [
                'value' => $value['value'],
                'option' => [
                    'name' => $value['option']['name'],
                ],
            ];
        }
        $data = $this->getSimpleTestData();
        $data['propertyValues'] = $properties;
        $data['filterGroupId'] = 1;
        $article = $this->resource->create($data);
        $this->resource->setResultMode(
            Resource::HYDRATE_ARRAY
        );
        $article = $this->resource->getOne($article->getId());
        foreach ($article['propertyValues'] as $value) {
            $this->assertTrue(in_array($value['id'], $valueIds));
            $this->assertTrue(in_array($value['optionId'], $optionIds));
        }
    }

    public function testCreateWithMultiplePropertiesAndNewGroup()
    {
        $data = $this->getSimpleTestData();

        $optionName = 'newOption' . uniqid(rand());
        $properties = [
            [
                'option' => ['name' => $optionName],
                'value' => 'testValue',
            ],
            [
                'option' => ['name' => $optionName],
                'value' => 'anotherTestValue',
            ],
        ];

        $data['propertyValues'] = $properties;
        $data['filterGroupId'] = 1;
        $article = $this->resource->create($data);
        $this->resource->setResultMode(
            Resource::HYDRATE_ARRAY
        );
        $articleId = $article->getId();
        $article = $this->resource->getOne($articleId);

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(['option'])->from('Shopware\Models\Property\Option', 'option')->where(
                'option.name = :optionName'
            )->setParameter('optionName', $optionName)->setFirstResult(0)->setMaxResults(20);
        $databaseValuesOptions = $builder->getQuery()->getArrayResult();

        $this->assertEquals($article['propertyValues'][0]['optionId'], $article['propertyValues'][1]['optionId']);
        $this->assertEquals(1, count($databaseValuesOptions));

        $this->resource->delete($articleId);

        //delete test values in s_filter_values
        $sql = 'DELETE FROM `s_filter_values` WHERE `optionId` = ?';
        Shopware()->Db()->query($sql, [$databaseValuesOptions[0]['id']]);

        //delete test values in s_filter_relations
        $sql = 'DELETE FROM `s_filter_relations` WHERE `optionId` = ?';
        Shopware()->Db()->query($sql, [$databaseValuesOptions[0]['id']]);

        //delete test values in s_filter_options
        $builder->delete('Shopware\Models\Property\Option', 'option')->andWhere(
                'option.name = :optionName'
            )->setParameter('optionName', $optionName)->getQuery()->execute();
    }

    public function testUpdateWithDuplicateProperties()
    {
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(['values', 'option'])->from('Shopware\Models\Property\Value', 'values')->innerJoin(
                'values.option',
                'option'
            )->setFirstResult(0)->setMaxResults(20);
        $databaseValues = $builder->getQuery()->getArrayResult();
        $properties = [];
        foreach ($databaseValues as $value) {
            $valueIds[] = $value['id'];
            $optionIds[] = $value['option']['id'];
            $properties[] = [
                'value' => $value['value'],
                'option' => [
                    'name' => $value['option']['name'],
                ],
            ];
        }
        $update = [
            'propertyValues' => $properties,
            'filterGroupId' => 1,
        ];
        $data = $this->getSimpleTestData();
        $this->resource->setResultMode(
            Resource::HYDRATE_OBJECT
        );
        $article = $this->resource->create($data);
        /** @var \$article Shopware\Models\Article\Article */
        $article = $this->resource->update($article->getId(), $update);
        /** @var $value \Shopware\Models\Property\Value */
        foreach ($article->getPropertyValues() as $value) {
            $this->assertTrue(in_array($value->getId(), $valueIds));
            $this->assertTrue(in_array($value->getOption()->getId(), $optionIds));
        }
    }

    public function testPriceReplacement()
    {
        $data = $this->getSimpleTestData();
        $article = $this->resource->create($data);

        $update = [
            'mainDetail' => [
                'number' => $article->getMainDetail()->getNumber(),
                '__options_prices' => ['replace' => false],
                'prices' => [
                    [
                        'customerGroupKey' => 'H',
                        'from' => 1,
                        'to' => '10',
                        'price' => 200,
                    ],
                    [
                        'customerGroupKey' => 'H',
                        'from' => 11,
                        'to' => '-',
                        'price' => 100,
                    ],
                ],
            ],
        ];

        $article = $this->resource->update($article->getId(), $update);
        $this->assertCount(3, $article->getMainDetail()->getPrices());
    }

    public function testUpdateWithMultiplePropertiesAndNewGroup()
    {
        $optionName = 'newOption' . uniqid(rand());
        $properties = [
            [
                'option' => ['name' => $optionName],
                'value' => 'testValue',
            ],
            [
                'option' => ['name' => $optionName],
                'value' => 'anotherTestValue',
            ],
        ];

        $update = [
            'propertyValues' => $properties,
            'filterGroupId' => 1,
        ];
        $data = $this->getSimpleTestData();
        $this->resource->setResultMode(
            Resource::HYDRATE_OBJECT
        );
        $article = $this->resource->create($data);
        /** @var $article \Shopware\Models\Article\Article */
        $article = $this->resource->update($article->getId(), $update);

        $articleId = $article->getId();
        $this->resource->setResultMode(
            Resource::HYDRATE_ARRAY
        );
        $article = $this->resource->getOne($article->getId());

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(['option'])->from('Shopware\Models\Property\Option', 'option')->where(
                'option.name = :optionName'
            )->setParameter('optionName', $optionName)->setFirstResult(0)->setMaxResults(20);
        $databaseValuesOptions = $builder->getQuery()->getArrayResult();

        $this->assertEquals($article['propertyValues'][0]['optionId'], $article['propertyValues'][1]['optionId']);
        $this->assertEquals(1, count($databaseValuesOptions));

        $this->resource->delete($articleId);

        //delete test values in s_filter_values
        $sql = 'DELETE FROM `s_filter_values` WHERE `optionId` = ?';
        Shopware()->Db()->query($sql, [$databaseValuesOptions[0]['id']]);

        //delete test values in s_filter_relations
        $sql = 'DELETE FROM `s_filter_relations` WHERE `optionId` = ?';
        Shopware()->Db()->query($sql, [$databaseValuesOptions[0]['id']]);

        //delete test values in s_filter_options
        $builder->delete('Shopware\Models\Property\Option', 'option')->andWhere(
                'option.name = :optionName'
            )->setParameter('optionName', $optionName)->getQuery()->execute();
    }

    public function testImageConfiguration()
    {
        $this->resource->setResultMode(
            Resource::HYDRATE_OBJECT
        );

        $create = $this->getSimpleTestData();

        $images = $this->getEntityOffset(
            'Shopware\Models\Media\Media',
            0,
            1,
            ['id as mediaId']
        );

        $configurator = $this->getSimpleConfiguratorSet(1, 2);
        $variants = $this->createConfiguratorVariants($configurator['groups']);

        $usedOption = $this->getOptionsForImage($configurator, 1, 'name');
        foreach ($images as &$image) {
            $image['options'] = [$usedOption];
        }

        $create['images'] = $images;
        $create['configuratorSet'] = $configurator;
        $create['variants'] = $variants;

        $article = $this->resource->create($create);

        /** @var $image \Shopware\Models\Article\Image */
        foreach ($article->getImages() as $image) {
            $this->assertCount(1, $image->getMappings());

            /** @var $mapping \Shopware\Models\Article\Image\Mapping */
            foreach ($image->getMappings() as $mapping) {
                $this->assertCount(1, $mapping->getRules());
            }
        }

        $this->resource->generateVariantImages($article->getId());

        $article = $this->resource->getOne($article->getId());

        /** @var $variant \Shopware\Models\Article\Detail */
        foreach ($article->getDetails() as $variant) {
            foreach ($variant->getConfiguratorOptions() as $option) {
                if ($option->getName() == $usedOption[0]['name']) {
                    $this->assertCount(1, $variant->getImages());
                }
            }
        }
    }

    public function testCategoryReplacement()
    {
        $this->internalTestReplaceMode(
            'Shopware\Models\Category\Category',
            'categories',
            true
        );
        $this->internalTestReplaceMode(
            'Shopware\Models\Category\Category',
            'categories',
            false
        );
    }

    public function testSimilarReplacement()
    {
        $this->internalTestReplaceMode(
            'Shopware\Models\Article\Article',
            'similar',
            true
        );
        $this->internalTestReplaceMode(
            'Shopware\Models\Article\Article',
            'similar',
            false
        );
    }

    public function testRelatedReplacement()
    {
        $this->internalTestReplaceMode(
            'Shopware\Models\Article\Article',
            'related',
            true
        );
        $this->internalTestReplaceMode(
            'Shopware\Models\Article\Article',
            'related',
            false
        );
    }

    public function testCustomerGroupReplacement()
    {
        $this->internalTestReplaceMode(
            'Shopware\Models\Customer\Group',
            'customerGroups',
            true
        );
        $this->internalTestReplaceMode(
            'Shopware\Models\Customer\Group',
            'customerGroups',
            false
        );
    }

    public function testArticleDefaultPriceBehavior()
    {
        $data = $this->getSimpleTestData();

        $article = $this->resource->create($data);

        $this->assertInstanceOf('Shopware\Models\Article\Article', $article);

        /** @var $price \Shopware\Models\Article\Price */
        $price = $article->getMainDetail()->getPrices()->first();

        $this->assertEquals(
            400 / (($article->getTax()->getTax() + 100) / 100),
            $price->getPrice(),
            'Customer group price not calculated'
        );

        $data = $this->resource->getOne($article->getId());

        $this->assertEquals(
            400 / (($article->getTax()->getTax() + 100) / 100),
            $data['mainDetail']['prices'][0]['price']
        );
    }

    public function testSimilarWithNumber()
    {
        $articles = $this->getEntityOffset('Shopware\Models\Article\Article', 0, 3);

        $data = $this->getSimpleTestData();
        $similar = [];
        foreach ($articles as $article) {
            $model = Shopware()->Models()->find(
                'Shopware\Models\Article\Article',
                $article['id']
            );

            $similar[] = ['number' => $model->getMainDetail()->getNumber()];
        }

        $data['similar'] = $similar;

        $article = $this->resource->create($data);

        $this->assertNotEmpty($article->getSimilar());
    }

    public function testRelatedWithNumber()
    {
        $articles = $this->getEntityOffset('Shopware\Models\Article\Article', 0, 3);

        $data = $this->getSimpleTestData();
        $similar = [];
        foreach ($articles as $article) {
            $model = Shopware()->Models()->find(
                'Shopware\Models\Article\Article',
                $article['id']
            );

            $similar[] = ['number' => $model->getMainDetail()->getNumber()];
        }

        $data['related'] = $similar;

        $article = $this->resource->create($data);

        $this->assertNotEmpty($article->getRelated());
    }

    public function testDownloads()
    {
        $data = $this->getSimpleTestData();

        $data['downloads'] = [
            ['link' => 'data:image/png;base64,' . require(__DIR__ . '/fixtures/base64image.php')],
        ];

        $article = $this->resource->create($data);

        $this->assertCount(1, $article->getDownloads());

        $downloads = [
            ['id' => $article->getDownloads()->first()->getId()],
            ['link' => 'file://' . __DIR__ . '/fixtures/variant-image.png'],
        ];

        $update = $this->resource->update(
            $article->getId(),
            [
                'downloads' => $downloads,
                '__options_downloads' => ['replace' => false],
            ]
        );

        $this->assertCount(2, $update->getDownloads());
    }

    public function testSeoCategories()
    {
        $data = $this->getSimpleTestData();

        $data['categories'] = Shopware()->Db()->fetchAll('SELECT DISTINCT id FROM s_categories LIMIT 5, 10');

        $first = $data['categories'][3];
        $second = $data['categories'][4];

        $ids = [$first['id'], $second['id']];

        $data['seoCategories'] = [
            ['shopId' => 1, 'categoryId' => $first['id']],
            ['shopId' => 2, 'categoryId' => $second['id']],
        ];

        $article = $this->resource->create($data);

        $this->resource->setResultMode(Resource::HYDRATE_OBJECT);

        /** @var $article \Shopware\Models\Article\Article */
        $article = $this->resource->getOne($article->getId());

        $this->assertCount(2, $article->getSeoCategories());

        foreach ($article->getSeoCategories() as $category) {
            $this->assertContains($category->getCategory()->getId(), $ids);
            $this->assertContains($category->getShop()->getId(), [1, 2]);
        }
    }

    public function testArticleGrossPrices()
    {
        $data = $this->getSimpleTestData();

        $article = $this->resource->create($data);

        $this->assertInstanceOf('Shopware\Models\Article\Article', $article);

        /** @var $price \Shopware\Models\Article\Price */
        $price = $article->getMainDetail()->getPrices()->first();

        $net = 400 / (($article->getTax()->getTax() + 100) / 100);

        $this->assertEquals(
            $net,
            $price->getPrice(),
            'Customer group price not calculated'
        );

        $this->resource->setResultMode(2);

        $data = $this->resource->getOne(
            $article->getId(),
            [
                'considerTaxInput' => true,
            ]
        );

        $price = $data['mainDetail']['prices'][0];

        $this->assertEquals(400, $price['price']);
        $this->assertEquals($net, $price['net']);
    }

    public function testAssignCategoriesByPathShouldBeSuccessful()
    {
        // Delete previous data
        try {
            $id = $this->resource->getIdFromNumber('hollo-1');
            if (!empty($id)) {
                $this->resource->delete($id);
            }
        } catch (\Exception $e) {
        }
        // Associate three kinds of categories with the article:
        // category by id, category by path, new category by path
        $article = $this->resource->create(
            [
                'name' => 'Hähnchenschnitzel Hollo',
                'active' => true,
                'tax' => 19,
                'supplier' => 'Onkel Tom',
                'categories' => [
                    ['path' => 'Deutsch|Genusswelten|Tees und Zubehör|Tees'],
                    ['path' => 'Deutsch|Genusswelten|Tees und Zubehör|Süßstoff'],
                    ['id' => 16],
                ],
                'mainDetail' => [
                    'number' => 'hollo-1',
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 4.99,
                        ],
                    ],
                ],
            ]
        );
        $ids = array_map(
            function ($category) {
                return $category->getId();
            },
            $article->getCategories()->toArray()
        );
        $ids = array_flip($ids);
        $this->assertArrayHasKey(12, $ids);
        $this->assertArrayHasKey(16, $ids);
        $this->assertCount(3, $ids);
    }

    public function testBatchModeShouldBeSuccessful()
    {
        $createNew = $this->getSimpleTestData();
        $updateExistingByNumber = [
            'mainDetail' => [
                'number' => 'SW10003',
            ],
            'keywords' => 'newKeyword1',
        ];
        $updateExistingById = [
            'id' => 3,
            'keywords' => 'newKeyword2',
        ];

        $result = $this->resource->batch(
            [
                'new' => $createNew,
                'existingByNumber' => $updateExistingByNumber,
                'existingById' => $updateExistingById,
            ]
        );

        $this->assertEquals('newKeyword1', $result['existingByNumber']['data']['keywords']);
        $this->assertEquals('newKeyword2', $result['existingById']['data']['keywords']);
        $this->assertEquals('Testartikel', $result['new']['data']['name']);
    }

    public function testBatchDeleteShouldBeSuccessful()
    {
        $result = $this->resource->batch(
            [
                $this->getSimpleTestData(),
                $this->getSimpleTestData(),
                $this->getSimpleTestData(),
            ]
        );

        $delete = [];
        foreach ($result as $item) {
            $delete[] = $item['data'];
        }

        $result = $this->resource->batchDelete($delete);

        $this->assertEquals(3, count($result));
    }

    public function testCategoryAssignment()
    {
        $number = 'CategoryAssignment' . uniqid(rand());

        $data = $this->getSimpleTestData();
        $data['mainDetail']['number'] = $number;

        $categories = Shopware()->Db()->fetchAll('SELECT id FROM s_categories WHERE parent = 3 ORDER BY id LIMIT 2');
        $data['categories'] = $categories;

        $article = $this->resource->create($data);

        $normal = Shopware()->Db()->fetchCol(
            'SELECT categoryID FROM s_articles_categories WHERE articleID = ?',
            [$article->getId()]
        );

        $denormalized = Shopware()->Db()->fetchCol(
            'SELECT categoryID FROM s_articles_categories_ro WHERE articleID = ?',
            [$article->getId()]
        );

        $this->assertCount(2, $normal);
        $this->assertCount(4, $denormalized);

        foreach ($categories as $category) {
            $this->assertContains($category['id'], $normal);
            $this->assertContains($category['id'], $denormalized);
        }

        $rewriteCategories = Shopware()->Db()->fetchAll(
            'SELECT id FROM s_categories WHERE parent = 3 ORDER BY id LIMIT 2, 2'
        );
        $data = [
            'categories' => $rewriteCategories,
        ];

        $this->resource->update($article->getId(), $data);

        $normal = Shopware()->Db()->fetchCol(
            'SELECT categoryID FROM s_articles_categories WHERE articleID = ?',
            [$article->getId()]
        );

        $denormalized = Shopware()->Db()->fetchCol(
            'SELECT categoryID FROM s_articles_categories_ro WHERE articleID = ?',
            [$article->getId()]
        );

        $this->assertCount(2, $normal);
        $this->assertCount(4, $denormalized);

        foreach ($rewriteCategories as $category) {
            $this->assertContains($category['id'], $normal);
            $this->assertContains(
                $category['id'],
                $denormalized,
                'Denormalized array contains not the expected category id'
            );
        }

        $additionally = Shopware()->Db()->fetchAll('SELECT id FROM s_categories WHERE parent = 3 ORDER BY id LIMIT 2');
        $data = [
            '__options_categories' => ['replace' => false],
            'categories' => $additionally,
        ];
        $this->resource->update($article->getId(), $data);

        $normal = Shopware()->Db()->fetchCol(
            'SELECT categoryID FROM s_articles_categories WHERE articleID = ?',
            [$article->getId()]
        );

        $denormalized = Shopware()->Db()->fetchCol(
            'SELECT categoryID FROM s_articles_categories_ro WHERE articleID = ?',
            [$article->getId()]
        );

        $this->assertCount(4, $normal);
        $this->assertCount(8, $denormalized);

        foreach ($rewriteCategories as $category) {
            $this->assertContains($category['id'], $normal);
            $this->assertContains(
                $category['id'],
                $denormalized,
                'Denormalized array contains not the expected category id'
            );
        }

        foreach ($additionally as $category) {
            $this->assertContains($category['id'], $normal);
            $this->assertContains(
                $category['id'],
                $denormalized,
                'Denormalized array contains not the expected category id'
            );
        }
    }

    public function testVariantImagesOnArticleCreate()
    {
        $data = [
            'descriptionLong' => 'test1',
            'name' => 'test1',
            'active' => true,
            'configuratorSet' => [
                'type' => 2,
                'groups' => [
                    [
                        'name' => 'New1',
                        'options' => [
                            ['name' => 'NewVal1'],
                            ['name' => 'Newval2'],
                        ],
                    ],
                ],
            ],
            'taxId' => 1,
            'mainDetail' => [
                'number' => uniqid(rand()),
                'active' => true,
                'prices' => [
                    [
                        'price' => 0.0,
                        'pseudoPrice' => 0.0,
                        'customerGroupKey' => 'EK',
                    ],
                ],
                'configuratorOptions' => [
                    [
                        'group' => 'New1',
                        'option' => 'NewVal1',
                    ],
                ],
                'shippingTime' => 7.0,
                'width' => 0,
                'inStock' => 2,
            ],
            'filterGroupId' => null,
            'images' => [
                    [
                        'position' => 0,
                        'main' => 1,
                        'mediaId' => 2,
                        'description' => '147quad1809 603994396334907 1063748094 n',
                        'options' => [
                            [
                                [
                                    'name' => 'NewVal1',
                                ],
                            ],
                        ],
                    ],
                    [
                        'position' => 0,
                        'main' => 2,
                        'mediaId' => 3,
                        'description' => 'IMG 7228',
                        'options' => [
                            [
                                [
                                    'name' => 'Newval2',
                                ],
                            ],
                        ],
                    ],
                ],
            'lastStock' => true,
            'variants' => [
                [
                    'number' => uniqid(rand()) . '.1',
                    'active' => true,
                    'prices' => [
                        [
                            'price' => 0.0,
                            'pseudoPrice' => 0.0,
                            'customerGroupKey' => 'EK',
                        ],
                    ],
                    'configuratorOptions' => [
                        [
                            'group' => 'New1',
                            'option' => 'Newval2',
                        ],
                    ],
                    'shippingTime' => 7.0,
                    'width' => 0,
                    'isMain' => 0,
                    'inStock' => 2,
                ],
            ],
        ];

        $article = $this->resource->create($data);

        /** @var Image $image */
        foreach ($article->getImages() as $image) {
            $media = $image->getMedia();

            $this->assertCount(1, $image->getMappings());

            /** @var Image\Mapping $mapping */
            $mapping = array_shift($image->getMappings()->getValues());

            $this->assertCount(1, $mapping->getRules());

            /** @var Image\Rule $rule */
            $rule = array_shift($mapping->getRules()->getValues());

            if ($media->getId() === 2) {
                $this->assertEquals('NewVal1', $rule->getOption()->getName());
            } elseif ($media->getId() === 3) {
                $this->assertEquals('Newval2', $rule->getOption()->getName());
            }
        }
    }

    /**
     * Combinations merge the result of dimensional arrays not perfectly
     * so we have to clean up the first array level.
     *
     * @param $combinations
     *
     * @return mixed
     */
    protected function cleanUpCombinations($combinations)
    {
        foreach ($combinations as &$combination) {
            $combination[] = [
                'option' => $combination['option'],
                'groupId' => $combination['groupId'],
            ];
            unset($combination['groupId']);
            unset($combination['option']);
        }

        return $combinations;
    }

    /**
     * Helper function which combines all array elements
     * of the passed arrays.
     *
     * @param $arrays
     * @param int $i
     *
     * @return array
     */
    protected function combinations($arrays, $i = 0)
    {
        if (!isset($arrays[$i])) {
            return [];
        }
        if ($i == count($arrays) - 1) {
            return $arrays[$i];
        }

        // get combinations from subsequent arrays
        $tmp = $this->combinations($arrays, $i + 1);

        $result = [];

        // concat each array from tmp with each element from $arrays[$i]
        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) {
                $result[] = is_array($t) ? array_merge([$v], $t) : [$v, $t];
            }
        }

        return $result;
    }

    protected function internalTestReplaceMode($entity, $arrayKey, $replace = true)
    {
        //create keys for getter function and the __options parameter in the update and create
        //example => "__options_categories"  /  "getCategories"
        $replaceKey = '__options_' . $arrayKey;
        $getter = 'get' . ucfirst($arrayKey);

        //returns a simple article data set to create an article with a simple main detail
        $data = $this->getSimpleTestData();

        //get an offset of 10 entities for the current entity type, like 10x categories
        $createdEntities = $this->getEntityOffset($entity);
        $data[$arrayKey] = $createdEntities;

        $article = $this->resource->create($data);
        $this->assertCount(count($createdEntities), $article->$getter());

        $updatedEntity = $this->getEntityOffset($entity, 20, 5, ['id']);

        $update = [
            $replaceKey => ['replace' => $replace],
            $arrayKey => $updatedEntity,
        ];
        $article = $this->resource->update($article->getId(), $update);

        if ($replace == true) {
            $this->assertCount(count($updatedEntity), $article->$getter());
        } else {
            $this->assertCount(count($createdEntities) + count($updatedEntity), $article->$getter());
        }
    }

    private function getOptionsForImage($configuratorSet, $optionCount = null, $property = 'id')
    {
        if (!is_int($optionCount)) {
            $optionCount = rand(1, count($configuratorSet['groups']) - 1);
        }

        $options = [];
        foreach ($configuratorSet['groups'] as $group) {
            $id = rand(0, count($group['options']) - 1);
            $option = $group['options'][$id];
            $options[] = [
                $property => $option[$property],
            ];
            if (count($options) == $optionCount) {
                return $options;
            }
        }

        return $options;
    }

    /**
     * Helper function which creates all variants for
     * the passed groups with options.
     *
     * @param $groups
     * @param array $groupMapping
     * @param array $optionMapping
     *
     * @return array
     */
    private function createConfiguratorVariants(
        $groups,
        $groupMapping = ['key' => 'groupId', 'value' => 'id'],
        $optionMapping = ['key' => 'option', 'value' => 'name']
    ) {
        $options = [];

        $groupArrayKey = $groupMapping['key'];
        $groupValuesKey = $groupMapping['value'];
        $optionArrayKey = $optionMapping['key'];
        $optionValuesKey = $optionMapping['value'];

        foreach ($groups as $group) {
            $groupOptions = [];
            foreach ($group['options'] as $option) {
                $groupOptions[] = [
                    $groupArrayKey => $group[$groupValuesKey],
                    $optionArrayKey => $option[$optionValuesKey],
                ];
            }
            $options[] = $groupOptions;
        }

        $combinations = $this->combinations($options);
        $combinations = $this->cleanUpCombinations($combinations);

        $variants = [];
        foreach ($combinations as $combination) {
            $variant = $this->getSimpleVariantData();
            $variant['configuratorOptions'] = $combination;
            $variants[] = $variant;
        }

        return $variants;
    }

    private function getImagesForNewArticle($offset = 10, $limit = 5)
    {
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(
            [
                'media.id as mediaId',
                '2 as main',
            ]
        )->from('Shopware\Models\Media\Media', 'media', 'media.id')->addOrderBy('media.id', 'ASC')->setFirstResult(
                $offset
            )->setMaxResults($limit);

        /**
         * Get random images.
         * Only want to check if the main flag will be used.
         */
        $images = $builder->getQuery()->getArrayResult();
        $keys = array_keys($images);
        $images[$keys[0]]['main'] = 1;

        return $images;
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

    private function getEntityOffset($entity, $offset = 0, $limit = 10, $fields = ['id'])
    {
        if (!empty($fields)) {
            $selectFields = [];
            foreach ($fields as $field) {
                $selectFields[] = 'alias.' . $field;
            }
        } else {
            $selectFields = ['alias'];
        }

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select($selectFields)->from($entity, 'alias')->setFirstResult($offset)->setMaxResults($limit);

        return $builder->getQuery()->getArrayResult();
    }

    private function getSimpleConfiguratorSet($groupLimit = 3, $optionLimit = 5)
    {
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(['groups.id', 'groups.name'])->from(
                'Shopware\Models\Article\Configurator\Group',
                'groups'
            )->setFirstResult(0)->setMaxResults($groupLimit)->orderBy('groups.position', 'ASC');

        $groups = $builder->getQuery()->getArrayResult();

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(['options.id', 'options.name'])->from(
                'Shopware\Models\Article\Configurator\Option',
                'options'
            )->where('options.groupId = :groupId')->setFirstResult(0)->setMaxResults($optionLimit)->orderBy(
                'options.position',
                'ASC'
            );

        foreach ($groups as &$group) {
            $builder->setParameter('groupId', $group['id']);
            $group['options'] = $builder->getQuery()->getArrayResult();
        }

        return [
            'name' => 'Test-Set',
            'groups' => $groups,
        ];
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
}
