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
    public const ID = 'AA-BB-CC';

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

    public function testInsertWithId()
    {
        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'id' => self::ID,
                    'name' => 'test',
                    'the_unknown_field' => 'do nothing?',
                    'taxId' => 'SWAG-TAX-ID-1',
                    'manufacturer' => ['id' => 'SWAG-PRODUCT-MANUFACTURER-ID-2', 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
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

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', [
            'id' => self::ID,
        ]);

        self::assertSame(self::ID, $product['id']);
    }

    public function testInsertWithoutId()
    {
        $productCountBefore = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');

        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'id' => 'detail-1',
                    'the_unknown_field' => 'do nothing?',
                    'taxId' => 'SWAG-TAX-ID-1',
                    'name' => 'foo',
                    'manufacturer' => ['id' => 'SWAG-PRODUCT-MANUFACTURER-ID-2', 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
                ],
            ],
            $this->createWriteContext()
        );

        $productCountAfter = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');
        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id NOT LIKE "SWAG-%"');

        self::assertSame($productCountBefore + 1, $productCountAfter);
        self::assertNotEmpty($product['id']);
    }

    public function testInsertFromDocs()
    {
        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'id' => self::ID,
                    'name' => 'ConfiguratorTest',
                    'description' => 'A test article',
                    'descriptionLong' => '<p>I\'m a <b>test article</b></p>',
                    'taxId' => 'SWAG-TAX-ID-1',
                    'manufacturer' => ['id' => 'SWAG-PRODUCT-MANUFACTURER-ID-2', 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
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
                        ['categoryId' => 'SWAG-CATEGORY-ID-1'],
                    ],

                    'prices' => [
                        [
                            'price' => (float) 999,
                            'customerGroupId' => StorefrontContextService::FALLBACK_CUSTOMER_GROUP,
                        ],
                    ],
                ],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', [
            'id' => self::ID,
        ]);

        self::assertSame(self::ID, $product['id']);
    }

    public function testUpdate()
    {
        $this->insertEmptyProduct();

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                [
                    'id' => self::ID,
                    'name' => '_THE_TITLE_',
                    'the_unknown_field' => 'do nothing?',
                    'description' => '<p>no html</p>',
                    'descriptionLong' => '<p>html</p>',
                    'availableFrom' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'availableTo' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'manufacturer' => [
                        'id' => 'SWAG-PRODUCT-MANUFACTURER-ID-1',
                        'link' => 'http://www.shopware.com',
                        'name' => 'Another Company',
                    ],
                ],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => self::ID]);
        $productManufacturer = $this->connection->fetchAssoc('SELECT * FROM product_manufacturer WHERE id=:id', ['id' => 'SWAG-PRODUCT-MANUFACTURER-ID-1']);
        $productManufacturerTranslation = $this->connection->fetchAssoc('SELECT * FROM product_manufacturer_translation WHERE product_manufacturer_id=:id', ['id' => 'SWAG-PRODUCT-MANUFACTURER-ID-1']);
        $productTranslation = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id=:id', ['id' => self::ID]);

        self::assertSame(self::ID, $product['id']);
        self::assertSame('_THE_TITLE_', $productTranslation['name'], print_r($productTranslation, true));
        self::assertSame('no html', $productTranslation['description']);
        self::assertSame('<p>html</p>', $productTranslation['description_long']);
        self::assertSame('SWAG-PRODUCT-MANUFACTURER-ID-1', $product['product_manufacturer_id']);
        self::assertSame('Another Company', $productManufacturerTranslation['name']);
        self::assertSame('http://www.shopware.com', $productManufacturer['link']);
    }

    public function testUpdateWritesDefaultColumnsIfOmmitted()
    {
        $this->insertEmptyProduct();

        $newProduct = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => self::ID]);

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                ['id' => self::ID, 'template' => 'ABC'],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => self::ID]);

        self::assertSame(self::ID, $product['id']);
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
                    'id' => self::ID,
                    'name' => [
                        'FFA32A50-E2D0-4CF3-8389-A53F8D6CD594' => '1ABC',
                        'SWAG-SHOP-ID-2' => '2ABC',
                    ],
                    'description' => 'foo', // implicit FFA32A50-E2D0-4CF3-8389-A53F8D6CD594
                    'descriptionLong' => [
                        'SWAG-SHOP-ID-2' => '2CBA',
                    ],
                    'translations' => [
                        'SWAG-SHOP-ID-2' => [
                            'name' => 'bar',
                            'description' => 'foo',
                            'keywords' => 'fiz,baz',
                        ],
                    ],
                    'metaTitle' => [
                        'SWAG-SHOP-ID-2' => 'bar',
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
//            'id' => self::ID,
//            'name' => '', // aus implicit,
//            'translations' => [],
//
//        ];
//
//        'GET /product/abc/translation/?' => 'indexAction'
//        'GET /product/abc/translation/%s' => 'detailAction'
//
//        'GET /product/abc/translation/en' => [
//            'productId' => 'abc',
//            'languageId' => 'SWAG-SHOP-ID-2',
//            'metaTitle' => 'bar',
//            'name' => '',
//            [...]
//        ]
//
//        'POST /product/abc' => [];

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => self::ID]);
        $productTranslations = $this->connection->fetchAll('SELECT * FROM product_translation WHERE product_id=:id ORDER BY language_id', ['id' => self::ID]);

        self::assertSame(self::ID, $product['id']);
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
                ['id' => self::ID, 'name' => $tooLongValue],
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
                    'id' => self::ID,
                    'name' => 'Test product',
                    'tax_id' => 'SWAG-TAX-ID-1',
                    'manufacturer' => [
                        'id' => 'SWAG-PRODUCT-MANUFACTURER-ID-2',
                        'name' => 'shopware AG',
                        'link' => 'https://shopware.com',
                    ],
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ],
            ],
            $this->createWriteContext()
        );
    }

    private function getWriter(): EntityWriterInterface
    {
        return self::$kernel->getContainer()->get('shopware.api.entity_writer');
    }
}
