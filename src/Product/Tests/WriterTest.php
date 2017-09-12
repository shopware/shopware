<?php declare(strict_types=1);

namespace Shopware\Product\Tests;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Write\FieldException\WriteStackException;
use Shopware\Framework\Write\WriteContext;
use Shopware\Framework\Write\Writer;
use Shopware\Product\Writer\ProductResource;
use Shopware\Shop\Writer\ShopResource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WriterTest extends KernelTestCase
{
    const UUID = 'AA-BB-CC';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var
     */
    private $productResource;

    public function setUp()
    {
        self::bootKernel();
        $container = self::$kernel->getContainer();
        $this->connection = $container->get('dbal_connection');
        $this->productResource = $container->get('shopware.product.product.resource');

        $this->connection->beginTransaction();
    }

    private function getWriter(): Writer
    {
        return self::$kernel->getContainer()->get('shopware.framework.write.writer');
    }

    public function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function test_insert_with_uuid()
    {
        $this->getWriter()->insert(ProductResource::class, [
                'uuid' => self::UUID,
                'name' => 'test',
                'the_unknown_field' => 'do nothing?',
                'taxUuid' => 'SWAG-TAX-UUID-1',
                'manufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2'],
                'mode' => 0,
                'lastStock' => true,
                'crossbundlelook' => 1,
                'notification' => true,
                'template' => 'foo',
                'updatedAt' => new \DateTime(),
                'active' => true,
                'details' => [
                    [
                        'uuid' => 'detail-1',
                        'ean' => '1',
                        'orderNumber' => 'foo',
                        'position' => 0,
                    ],
                    [
                        'uuid' => 'detail-2',
                        'ean' => '55',
                        'orderNumber' => 'bar',
                        'position' => 1,
                    ],
                ]
            ],
            $this->createWriteContext(),
            $this->createExtender());

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', [
            'uuid' => self::UUID,
        ]);

        self::assertSame(self::UUID, $product['uuid']);
    }

    public function test_insert_without_uuid()
    {
        $productCountBefore = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');

        $this->getWriter()->insert(ProductResource::class, [
            'the_unknown_field' => 'do nothing?',
            'taxUuid' => 'SWAG-TAX-UUID-1',
            'name' => 'foo',
            'manufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2'],
            'mode' => 0,
            'updatedAt' => new \DateTime(),
            'lastStock' => true,
            'crossbundlelook' => 1,
            'notification' => true,
            'template' => 'foo',
        ], $this->createWriteContext(), $this->createExtender());

        $productCountAfter = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');
        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid NOT LIKE "SWAG-%"');

        self::assertSame($productCountBefore + 1, $productCountAfter);
        self::assertNotEmpty($product['uuid']);
    }

    public function test_insert_from_docs()
    {
        $this->getWriter()->insert(ProductResource::class, [
            'uuid' => self::UUID,
            'name' => 'ConfiguratorTest',
            'description' => 'A test article',
            'descriptionLong' => '<p>I\'m a <b>test article</b></p>',
            'taxUuid' => 'SWAG-TAX-UUID-1',
            'manufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2'],
            'updatedAt' => new \DateTime(),
            'mode' => 0,
            'lastStock' => true,
            'crossbundlelook' => 1,
            'notification' => false,
            'template' => 'foo',
            'active' => true,

            'categories' => [
                ['categoryUuid' => 'SWAG-CATEGORY-UUID-25'],
            ],

            'details' => [
                [
                    'isMain' => true,
                    'uuid' => 'swTEST' . uniqid(),
                    'inStock' => 15,
                    'additionaltext' => 'S / Schwarz',
                    'position' => 0,
                    'prices' => [
                        [
                            'pricegroup' => 'EK',
                            'price' => (float) 999,
                        ],
                    ]
                ],
                [
                    'number' => 'swTEST' . uniqid(),
                    'inStock' => 10,
                    'position' => 0,
                    'additionaltext' => 'S / Weiß',
                    'prices' => [
                        [
                            'pricegroup' => 'EK',
                            'price' => (float) 888,
                        ],
                    ],
                ],
                [
                    'number' => 'swTEST' . uniqid(),
                    'inStock' => 5,
                    'additionaltext' => 'XL / Blue',
                    'position' => 0,
                    'prices' => [
                        [
                            'pricegroup' => 'EK',
                            'price' => (float) 555,
                        ],
                    ]
                ]
            ],
        ], $this->createWriteContext(), $this->createExtender());

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', [
            'uuid' => self::UUID,
        ]);

        self::assertSame(self::UUID, $product['uuid']);
    }

    public function test_update()
    {
        $this->insertEmptyProduct();

        $this->getWriter()->update(ProductResource::class, [
            'uuid' => self::UUID,
            'name' => '_THE_TITLE_',
            'the_unknown_field' => 'do nothing?',
            'description' => '<p>no html</p>',
            'descriptionLong' => '<p>html</p>',
            'availableFrom' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            'availableTo' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            'updatedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            'createdAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            'manufacturer' => [
                'uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-1',
                'link' => 'http://www.shopware.com',
            ],
            'details' => [
                [
                    'uuid' => 'detail-1',
                    'ean' => '1',
                    'orderNumber' => 'foo',
                    'position' => 0,
                ],
                [
                    'uuid' => 'detail-2',
                    'ean' => '55',
                    'orderNumber' => 'bar',
                    'position' => 1,
                ],
            ]
        ], $this->createWriteContext(), $this->createExtender());

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', ['uuid' => self::UUID]);
        $productManufacturer = $this->connection->fetchAssoc('SELECT * FROM product_manufacturer WHERE uuid=:uuid', ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-1']);
        $productDetails = $this->connection->fetchAll('SELECT * FROM product_detail WHERE product_uuid = :uuid', ['uuid' => self::UUID]);
        $productTranslation = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_uuid=:uuid', ['uuid' => self::UUID]);

        self::assertSame(self::UUID, $product['uuid']);
        self::assertSame('_THE_TITLE_', $productTranslation['name'], print_r($productTranslation, true));
        self::assertSame('2011-01-01 15:03:01', $product['available_from']);
        self::assertSame('2011-01-01 15:03:01', $product['available_to']);
        self::assertSame('no html', $productTranslation['description']);
        self::assertSame('<p>html</p>', $productTranslation['description_long']);
        self::assertSame('SWAG-PRODUCT-MANUFACTURER-UUID-1', $product['product_manufacturer_uuid']);
        self::assertEquals('2011-01-01 15:03:01', $product['updated_at']);
        self::assertEquals('2011-01-01 15:03:01', $product['created_at']);
        self::assertSame('shopware AG', $productManufacturer['name']);
        self::assertSame('http://www.shopware.com', $productManufacturer['link']);
        self::assertCount(2, $productDetails, print_r($productDetails, true));
    }

    public function test_update_writes_default_columns_if_ommitted()
    {
        $this->insertEmptyProduct();

        $newProduct = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', ['uuid' => self::UUID]);

        $this->getWriter()->update(ProductResource::class, [
            'uuid' => self::UUID,
            'template' => 'ABC',
        ], $this->createWriteContext(), $this->createExtender());

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', ['uuid' => self::UUID]);

        self::assertSame(self::UUID, $product['uuid']);
        self::assertSame('ABC', $product['template']);

        self::assertNotEquals('0000-00-00 00:00:00', $product['updated_at']);
        self::assertNotEquals('2011-01-01 15:03:01', $product['updated_at']);

        self::assertNotEquals('0000-00-00 00:00:00', $product['created_at']);
        self::assertNotEquals('2011-01-01 15:03:01', $product['created_at']);
        self::assertNotEquals('0000-00-00 00:00:00', $newProduct['created_at']);
        self::assertNotEquals('2011-01-01 15:03:01', $newProduct['created_at']);
    }

    public function test_update_writes_multiple_translations()
    {
        $this->insertEmptyProduct();

        $this->getWriter()->update(ProductResource::class, [
            'uuid' => self::UUID,
            'name' => [
                'SWAG-SHOP-UUID-1' => '1ABC',
                'SWAG-SHOP-UUID-2' => '2ABC',
            ],
            'description' => 'foo', // implicit SWAG-SHOP-UUID-1
            'descriptionLong' => [
                'SWAG-SHOP-UUID-2' => '2CBA',
            ],
            'translations' => [
                'SWAG-SHOP-UUID-2' => [
                    'name' => 'bar',
                    'description' => 'foo',
                    'keywords' => 'fiz,baz'
                ]
            ],
            'metaTitle' => [
                'SWAG-SHOP-UUID-2' => 'bar',
            ]
        ], $this->createWriteContext(), $this->createExtender());

//        'POST auth/login' => [
//            'localeList' => ''
//        ];
//
//        'POST login/auth/language/' {locale: en} => [
//
//        ];
//
//        'GET /product/abc' => [
//            'uuid' => self::UUID,
//            'name' => '', // aus implicit,
//            'translations' => [],
//
//        ];
//
//        'GET /product/abc/translation/?' => 'indexAction'
//        'GET /product/abc/translation/%s' => 'detailAction'
//
//        'GET /product/abc/translation/en' => [
//            'productUuid' => 'abc',
//            'languageUuid' => 'SWAG-SHOP-UUID-2',
//            'metaTitle' => 'bar',
//            'name' => '',
//            [...]
//        ]
//
//        'POST /product/abc' => [];

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', ['uuid' => self::UUID]);
        $productTranslations = $this->connection->fetchAll('SELECT * FROM product_translation WHERE product_uuid=:uuid ORDER BY language_uuid', ['uuid' => self::UUID]);

        self::assertSame(self::UUID, $product['uuid']);
        self::assertCount(2, $productTranslations, print_r($productTranslations, true));
        self::assertSame('1ABC', $productTranslations[0]['name']);
        self::assertSame('2ABC', $productTranslations[1]['name']);
        self::assertSame('foo', $productTranslations[0]['description']);
        self::assertSame('foo', $productTranslations[1]['description']);
        self::assertNull($productTranslations[0]['description_long']);
        self::assertSame('2CBA', $productTranslations[1]['description_long']);
        self::assertNull($productTranslations[0]['meta_title']);
        self::assertSame('bar', $productTranslations[1]['meta_title']);
        self::assertNull($productTranslations[0]['keywords']);
        self::assertSame('fiz,baz', $productTranslations[1]['keywords']);
    }

    public function test_update_invalid()
    {
        $this->insertEmptyProduct();

        $tooLongValue = '';
        for($i = 0; $i < 512; $i++) {
            $tooLongValue .= '#';
        }

        $this->expectException(WriteStackException::class);
        $this->getWriter()->update(ProductResource::class, [
            'uuid' => self::UUID,
            'name' => $tooLongValue,
        ], $this->createWriteContext(), $this->createExtender());
    }

    /**
     * @return WriteContext
     */
    protected function createWriteContext(): WriteContext
    {
        $context = new WriteContext();
        $context->set(ShopResource::class, 'uuid', 'SWAG-SHOP-UUID-1');
        return $context;
    }

    protected function insertEmptyProduct(): void
    {
        $this->connection->insert(
            'product',
            [
                'uuid' => self::UUID,
                'tax_uuid' => 'SWAG-TAX-UUID-1',
                'product_manufacturer_uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2',
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'last_stock' => 0,
//                'crossbundlelook' => 0,
                'notification' => 0,
                'template' => 0,
                'mode' => 0,
            ]);
    }

    /**
     * @return FieldExtenderCollection
     */
    private function createExtender(): FieldExtenderCollection
    {
        $extender = new FieldExtenderCollection();
        $extender->addExtender(self::$kernel->getContainer()->get('shopware.framework.write.field_aware.default_extender'));
        return $extender;
    }
}