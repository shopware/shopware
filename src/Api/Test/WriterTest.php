<?php declare(strict_types=1);

namespace Shopware\Api\Test;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\FieldException\WriteStackException;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Storefront\Context\StorefrontContextService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WriterTest extends KernelTestCase
{
    public const UUID = 'AA-BB-CC';

    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        self::bootKernel();
        $container = self::$kernel->getContainer();
        $this->connection = $container->get('dbal_connection');
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testInsertWithUuid()
    {
        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'uuid' => self::UUID,
                    'name' => 'test',
                    'the_unknown_field' => 'do nothing?',
                    'taxUuid' => 'SWAG-TAX-UUID-1',
                    'manufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2', 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
                    'mode' => 0,
                    'lastStock' => true,
                    'crossbundlelook' => 1,
                    'notification' => true,
                    'template' => 'foo',
                    'updatedAt' => new \DateTime(),
                    'active' => true,
                ],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', [
            'uuid' => self::UUID,
        ]);

        self::assertSame(self::UUID, $product['uuid']);
    }

    public function testInsertWithoutUuid()
    {
        $productCountBefore = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');

        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'uuid' => 'detail-1',
                    'the_unknown_field' => 'do nothing?',
                    'taxUuid' => 'SWAG-TAX-UUID-1',
                    'name' => 'foo',
                    'manufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2', 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
                ],
            ],
            $this->createWriteContext()
        );

        $productCountAfter = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');
        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid NOT LIKE "SWAG-%"');

        self::assertSame($productCountBefore + 1, $productCountAfter);
        self::assertNotEmpty($product['uuid']);
    }

    public function testInsertFromDocs()
    {
        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'uuid' => self::UUID,
                    'name' => 'ConfiguratorTest',
                    'description' => 'A test article',
                    'descriptionLong' => '<p>I\'m a <b>test article</b></p>',
                    'taxUuid' => 'SWAG-TAX-UUID-1',
                    'manufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2', 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
                    'updatedAt' => new \DateTime(),
                    'mode' => 0,
                    'lastStock' => true,
                    'crossbundlelook' => 1,
                    'notification' => false,
                    'template' => 'foo',
                    'active' => true,
                    'additionaltext' => 'S / Schwarz',
                    'inStock' => 15,
                    'isMain' => true,

                    'categories' => [
                        ['categoryUuid' => 'SWAG-CATEGORY-UUID-1'],
                    ],

                    'prices' => [
                        [
                            'price' => (float) 999,
                            'customerGroupUuid' => StorefrontContextService::FALLBACK_CUSTOMER_GROUP,
                        ],
                    ],
                ],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', [
            'uuid' => self::UUID,
        ]);

        self::assertSame(self::UUID, $product['uuid']);
    }

    public function testUpdate()
    {
        $this->insertEmptyProduct();

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                [
                    'uuid' => self::UUID,
                    'name' => '_THE_TITLE_',
                    'the_unknown_field' => 'do nothing?',
                    'description' => '<p>no html</p>',
                    'descriptionLong' => '<p>html</p>',
                    'availableFrom' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'availableTo' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'manufacturer' => [
                        'uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-1',
                        'link' => 'http://www.shopware.com',
                        'name' => 'Another Company'
                    ],
                ],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', ['uuid' => self::UUID]);
        $productManufacturer = $this->connection->fetchAssoc('SELECT * FROM product_manufacturer WHERE uuid=:uuid', ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-1']);
        $productManufacturerTranslation = $this->connection->fetchAssoc('SELECT * FROM product_manufacturer_translation WHERE product_manufacturer_uuid=:uuid', ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-1']);
        $productTranslation = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_uuid=:uuid', ['uuid' => self::UUID]);

        self::assertSame(self::UUID, $product['uuid']);
        self::assertSame('_THE_TITLE_', $productTranslation['name'], print_r($productTranslation, true));
        self::assertSame('no html', $productTranslation['description']);
        self::assertSame('<p>html</p>', $productTranslation['description_long']);
        self::assertSame('SWAG-PRODUCT-MANUFACTURER-UUID-1', $product['product_manufacturer_uuid']);
        self::assertSame('Another Company', $productManufacturerTranslation['name']);
        self::assertSame('http://www.shopware.com', $productManufacturer['link']);
    }

    public function testUpdateWritesDefaultColumnsIfOmmitted()
    {
        $this->insertEmptyProduct();

        $newProduct = $this->connection->fetchAssoc('SELECT * FROM product WHERE uuid=:uuid', ['uuid' => self::UUID]);

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                ['uuid' => self::UUID, 'template' => 'ABC'],
            ],
            $this->createWriteContext()
        );

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

    public function testUpdateWritesMultipleTranslations()
    {
        $this->insertEmptyProduct();

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                [
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
                            'keywords' => 'fiz,baz',
                        ],
                    ],
                    'metaTitle' => [
                        'SWAG-SHOP-UUID-2' => 'bar',
                    ],
                ],
            ],
            $this->createWriteContext()
        );

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

    public function testUpdateInvalid()
    {
        $this->insertEmptyProduct();

        $tooLongValue = '';
        for ($i = 0; $i < 512; ++$i) {
            $tooLongValue .= '#';
        }

        $this->expectException(WriteStackException::class);
        $this->getWriter()->update(
            ProductDefinition::class,
            [
                ['uuid' => self::UUID, 'name' => $tooLongValue],
            ],
            $this->createWriteContext()
        );
    }

    /**
     * @return WriteContext
     */
    protected function createWriteContext(): WriteContext
    {
        $context = WriteContext::createFromTranslationContext(
            TranslationContext::createDefaultContext()
        );

        return $context;
    }

    protected function insertEmptyProduct(): void
    {
        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'uuid' => self::UUID,
                    'name' => 'Test product',
                    'tax_uuid' => 'SWAG-TAX-UUID-1',
                    'manufacturer' => [
                        'uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2',
                        'name' => 'shopware AG',
                        'link' => 'https://shopware.com'
                    ],
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]
            ],
            $this->createWriteContext()
        );
    }

    private function getWriter(): EntityWriterInterface
    {
        return self::$kernel->getContainer()->get('shopware.api.entity_writer');
    }
}
