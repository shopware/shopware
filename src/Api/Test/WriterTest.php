<?php declare(strict_types=1);

namespace Shopware\Api\Test;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\FieldException\WriteStackException;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Category\Extension\CategoryPathBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Storefront\Context\StorefrontContextService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WriterTest extends KernelTestCase
{
    public $id;

    /**
     * @var Connection
     */
    private $connection;
    private $idBytes;

    public function setUp()
    {
        self::bootKernel();
        $this->id = Uuid::uuid4()->toString();
        $this->idBytes = Uuid::fromString($this->id)->getBytes();

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
                    'id' => $this->id,
                    'name' => 'test',
                    'the_unknown_field' => 'do nothing?',
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'manufacturer' => ['id' => Uuid::uuid4()->toString(), 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
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
            'id' => $this->idBytes,
        ]);

        self::assertNotEmpty($product['id']);
    }

    public function testInsertWithoutId()
    {
        $productCountBefore = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');

        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'the_unknown_field' => 'do nothing?',
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'name' => 'foo',
                    'manufacturer' => ['id' => Uuid::uuid4()->toString(), 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
                ],
            ],
            $this->createWriteContext()
        );

        $productCountAfter = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');

        self::assertSame($productCountBefore + 1, $productCountAfter);
    }

    public function testInsertFromDocs()
    {
        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'id' => $this->id,
                    'name' => 'ConfiguratorTest',
                    'description' => 'A test article',
                    'descriptionLong' => '<p>I\'m a <b>test article</b></p>',
                    'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'manufacturer' => ['id' => Uuid::uuid4()->toString(), 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
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
                        ['categoryId' => CategoryPathBuilder::ROOT],
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
            'id' => $this->idBytes,
        ]);

        self::assertNotEmpty($product);
    }

    public function testUpdate()
    {
        $this->insertEmptyProduct();

        $productManufacturerId = Uuid::uuid4()->toString();

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                [
                    'id' => $this->id,
                    'name' => '_THE_TITLE_',
                    'the_unknown_field' => 'do nothing?',
                    'description' => '<p>no html</p>',
                    'descriptionLong' => '<p>html</p>',
                    'availableFrom' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'availableTo' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'manufacturer' => [
                        'id' => $productManufacturerId,
                        'link' => 'http://www.shopware.com',
                        'name' => 'Another Company',
                    ],
                ],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);
        $productManufacturer = $this->connection->fetchAssoc('SELECT * FROM product_manufacturer WHERE id=:id', ['id' => Uuid::fromString($productManufacturerId)->getBytes()]);
        $productManufacturerTranslation = $this->connection->fetchAssoc('SELECT * FROM product_manufacturer_translation WHERE product_manufacturer_id=:id', ['id' => Uuid::fromString($productManufacturerId)->getBytes()]);
        $productTranslation = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id=:id', ['id' => $this->idBytes]);

        self::assertSame('_THE_TITLE_', $productTranslation['name'], print_r($productTranslation, true));
        self::assertSame('no html', $productTranslation['description']);
        self::assertSame('<p>html</p>', $productTranslation['description_long']);
        self::assertSame('Another Company', $productManufacturerTranslation['name']);
        self::assertSame('http://www.shopware.com', $productManufacturer['link']);
    }

    public function testUpdateWritesDefaultColumnsIfOmmitted()
    {
        $this->insertEmptyProduct();

        $newProduct = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                ['id' => $this->id, 'template' => 'ABC'],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);

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
                    'id' => $this->id,
                    'name' => [
                        'FFA32A50-E2D0-4CF3-8389-A53F8D6CD594' => '1ABC',
                        '2d905256-e751-4967-8dd5-a32a81b94f1f' => '2ABC',
                    ],
                    'description' => 'foo', // implicit FFA32A50-E2D0-4CF3-8389-A53F8D6CD594
                    'descriptionLong' => [
                        '2d905256-e751-4967-8dd5-a32a81b94f1f' => '2CBA',
                    ],
                    'translations' => [
                        '2d905256-e751-4967-8dd5-a32a81b94f1f' => [
                            'name' => 'bar',
                            'description' => 'foo',
                            'keywords' => 'fiz,baz',
                        ],
                    ],
                    'metaTitle' => [
                        '2d905256-e751-4967-8dd5-a32a81b94f1f' => 'bar',
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
//            'id' => $this->id,
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
//            'languageId' => '2d905256-e751-4967-8dd5-a32a81b94f1f',
//            'metaTitle' => 'bar',
//            'name' => '',
//            [...]
//        ]
//
//        'POST /product/abc' => [];

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);
        $productTranslations = $this->connection->fetchAll('SELECT * FROM product_translation WHERE product_id= :id', ['id' => $this->idBytes]);

        self::assertNotEmpty($product);

        self::assertCount(2, $productTranslations, print_r($productTranslations, true));

        $productTranslations = array_map(function ($a) {
            $a['language_id'] = Uuid::fromBytes($a['language_id'])->toString();

            return $a;
        }, $productTranslations);

        foreach ($productTranslations as $translation) {
            if ($translation['language_id'] === 'ffa32a50-e2d0-4cf3-8389-a53f8d6cd594') {
                self::assertSame('1ABC', $translation['name']);
                self::assertSame('foo', $translation['description']);
                self::assertNull($translation['description_long']);
                self::assertNull($translation['meta_title']);
                self::assertNull($translation['keywords']);
            } else {
                self::assertSame('2ABC', $translation['name']);
                self::assertSame('foo', $translation['description']);
                self::assertSame('2CBA', $translation['description_long']);
                self::assertSame('bar', $translation['meta_title']);
                self::assertSame('fiz,baz', $translation['keywords']);
            }
        }
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
                ['id' => $this->id, 'name' => $tooLongValue],
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
                    'id' => $this->id,
                    'name' => 'Test product',
                    'tax_id' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                    'manufacturer' => [
                        'id' => Uuid::uuid4()->toString(),
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
