<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\IncompletePrimaryKeyException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Tax\TaxDefinition;

class WriterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public $id;

    /**
     * @var Connection
     */
    private $connection;
    private $idBytes;

    protected function setUp(): void
    {
        $this->id = Uuid::uuid4()->getHex();
        $this->idBytes = Uuid::fromStringToBytes($this->id);

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testDelete(): void
    {
        $id = Uuid::uuid4();

        $context = $this->createWriteContext();

        $this->getWriter()->insert(
            CategoryDefinition::class,
            [
                ['id' => $id->getHex(), 'name' => 'test-country'],
            ],
            $context
        );

        $exists = $this->connection->fetchAll('SELECT * FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        static::assertNotEmpty($exists);

        $deleteResult = $this->getWriter()->delete(
            CategoryDefinition::class,
            [
                ['id' => $id->getHex()],
            ],
            $context
        );

        $exists = $this->connection->fetchAll('SELECT * FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        static::assertEmpty($exists);
        static::assertEmpty($deleteResult->getNotFound());
        static::assertNotEmpty($deleteResult->getDeleted());
    }

    public function testDeleteWithoutIds(): void
    {
        $this->expectException('InvalidArgumentException');
        $taxRepository = $this->getTaxRepository();

        $taxRepository->delete([], Context::createDefaultContext());
    }

    public function testMultiDelete(): void
    {
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();

        $context = $this->createWriteContext();

        $this->getWriter()->insert(
            CategoryDefinition::class,
            [
                ['id' => $id->getHex(), 'name' => 'test-country1'],
                ['id' => $id2->getHex(), 'name' => 'test-country2'],
            ],
            $context
        );

        $categories = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [$id->getBytes(), $id2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(2, $categories);

        $translations = $this->connection->fetchAll(
            'SELECT * FROM category_translation WHERE category_id IN (:id) ',
            ['id' => [$id->getBytes(), $id2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(2, $translations);

        $deleteResult = $this->getWriter()->delete(
            CategoryDefinition::class,
            [
                ['id' => $id->getHex()],
                ['id' => $id2->getHex()],
            ],
            $context
        );
        static::assertEmpty($deleteResult->getNotFound());
        static::assertNotEmpty($deleteResult->getDeleted()[CategoryDefinition::class]);

        $categories = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [$id->getBytes(), $id2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );

        static::assertEmpty($categories);

        $translations = $this->connection->fetchAll(
            'SELECT * FROM category_translation WHERE category_id IN (:id) ',
            ['id' => [$id->getBytes(), $id2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );

        static::assertEmpty($translations);
    }

    public function testMultiDeleteWithNoneExistingId(): void
    {
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();

        $context = $this->createWriteContext();

        $this->getWriter()->insert(
            CategoryDefinition::class,
            [
                ['id' => $id->getHex(), 'name' => 'test-country1'],
                ['id' => $id2->getHex(), 'name' => 'test-country2'],
            ],
            $context
        );

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [$id->getBytes(), $id2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(2, $exists);

        $deleteResult = $this->getWriter()->delete(
            CategoryDefinition::class,
            [
                ['id' => $id->getHex()],
                ['id' => $id2->getHex()],
                ['id' => Uuid::uuid4()->getHex()],
                ['id' => Uuid::uuid4()->getHex()],
                ['id' => Uuid::uuid4()->getHex()],
            ],
            $context
        );

        static::assertCount(3, $deleteResult->getNotFound()[CategoryDefinition::class]);
        static::assertCount(2, $deleteResult->getDeleted()[CategoryDefinition::class]);

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [$id->getBytes(), $id2->getBytes()]],
            ['id' => Connection::PARAM_STR_ARRAY]
        );

        static::assertEmpty($exists);
    }

    public function testDeleteWithMultiplePrimaryColumns(): void
    {
        $productId = Uuid::uuid4();
        $categoryId = Uuid::uuid4();

        $context = $this->createWriteContext();
        $this->getWriter()->insert(ProductDefinition::class, [
            [
                'id' => $productId->getHex(),
                'name' => 'test 1',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId->getHex(), 'name' => 'test'],
                ],
            ],
        ], $context);

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id = :product AND category_id = :category',
            ['product' => $productId->getBytes(), 'category' => $categoryId->getBytes()]
        );
        static::assertCount(1, $exists);

        $deleteResult = $this->getWriter()->delete(ProductCategoryDefinition::class, [
            ['productId' => $productId->getHex(), 'categoryId' => $categoryId->getHex()],
        ], $context);

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id = :product AND category_id = :category',
            ['product' => $productId->getBytes(), 'category' => $categoryId->getBytes()]
        );
        static::assertEmpty($exists);

        static::assertCount(1, $deleteResult->getDeleted()[ProductCategoryDefinition::class]);
        static::assertCount(0, $deleteResult->getNotFound());
    }

    public function testRequiresAllPrimaryKeyValuesForDelete(): void
    {
        $this->expectException(IncompletePrimaryKeyException::class);

        $productId = Uuid::uuid4();

        $this->getWriter()->delete(ProductCategoryDefinition::class, [
            ['productId' => $productId->getHex()],
        ], $this->createWriteContext());
    }

    public function testMultiDeleteWithMultiplePrimaryColumns(): void
    {
        $productId = Uuid::uuid4();
        $productId2 = Uuid::uuid4();
        $categoryId = Uuid::uuid4();

        $context = $this->createWriteContext();
        $this->getWriter()->insert(ProductDefinition::class, [
            [
                'id' => $productId->getHex(),
                'name' => 'test 1',
                'price' => ['gross' => 10, 'net' => 8.10],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId->getHex(), 'name' => 'test'],
                ],
            ],
            [
                'id' => $productId2->getHex(),
                'name' => 'test 1',
                'price' => ['gross' => 10, 'net' => 8.10],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId->getHex()],
                ],
            ],
            [
                'name' => 'test 1',
                'price' => ['gross' => 10, 'net' => 8.10],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['name' => 'test'],
                ],
            ],
        ], $context);

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id IN (:product) AND category_id = :category',
            ['product' => [$productId->getBytes(), $productId2->getBytes()], 'category' => $categoryId->getBytes()],
            ['product' => Connection::PARAM_STR_ARRAY]
        );
        static::assertCount(2, $exists);

        $deleteResult = $this->getWriter()->delete(ProductCategoryDefinition::class, [
            ['productId' => $productId->getHex(), 'categoryId' => $categoryId->getHex()],
            ['productId' => $productId2->getHex(), 'categoryId' => $categoryId->getHex()],
        ], $context);

        $exists = $this->connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id IN (:product) AND category_id = :category',
            ['product' => [$productId->getBytes(), $productId2->getBytes()], 'category' => $categoryId->getBytes()],
            ['product' => Connection::PARAM_STR_ARRAY]
        );
        static::assertEmpty($exists);

        static::assertCount(2, $deleteResult->getDeleted()[ProductCategoryDefinition::class]);
        static::assertCount(0, $deleteResult->getNotFound());
    }

    public function testInsertWithId(): void
    {
        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'id' => $this->id,
                    'name' => 'test',
                    'price' => ['gross' => 10, 'net' => 8.10],
                    'the_unknown_field' => 'do nothing?',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['id' => Uuid::uuid4()->getHex(), 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
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

        static::assertNotEmpty($product['id']);
    }

    public function testInsertWithoutId(): void
    {
        $productCountBefore = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');

        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'the_unknown_field' => 'do nothing?',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'name' => 'foo',
                    'price' => ['gross' => 10, 'net' => 8.10],
                    'manufacturer' => ['id' => Uuid::uuid4()->getHex(), 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
                ],
            ],
            $this->createWriteContext()
        );

        $productCountAfter = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM product');

        static::assertSame($productCountBefore + 1, $productCountAfter);
    }

    public function testInsertFromDocs(): void
    {
        $this->getWriter()->insert(
            ProductDefinition::class,
            [
                [
                    'id' => $this->id,
                    'name' => 'ConfiguratorTest',
                    'description' => 'A test article',
                    'descriptionLong' => '<p>I\'m a <b>test article</b></p>',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['id' => Uuid::uuid4()->getHex(), 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
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
                        ['name' => 'Some category'],
                    ],
                    'price' => ['gross' => 10, 'net' => 8.10],
                ],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', [
            'id' => $this->idBytes,
        ]);

        static::assertNotEmpty($product);
    }

    public function testUpdate(): void
    {
        $this->insertEmptyProduct();

        $productManufacturerId = Uuid::uuid4()->getHex();

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                [
                    'id' => $this->id,
                    'name' => '_THE_TITLE_',
                    'the_unknown_field' => 'do nothing?',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'description' => '<p>no html</p>',
                    'descriptionLong' => '<p>html</p>',
                    'availableFrom' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'availableTo' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'price' => ['gross' => 10, 'net' => 8.10],
                    'manufacturer' => [
                        'id' => $productManufacturerId,
                        'link' => 'http://www.shopware.com',
                        'name' => 'Another Company',
                    ],
                ],
            ],
            $this->createWriteContext()
        );

        $productManufacturer = $this->connection->fetchAssoc('SELECT * FROM product_manufacturer WHERE id=:id', ['id' => Uuid::fromStringToBytes($productManufacturerId)]);
        $productManufacturerTranslation = $this->connection->fetchAssoc('SELECT * FROM product_manufacturer_translation WHERE product_manufacturer_id=:id', ['id' => Uuid::fromStringToBytes($productManufacturerId)]);
        $productTranslation = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id=:id', ['id' => $this->idBytes]);

        static::assertSame('_THE_TITLE_', $productTranslation['name'], print_r($productTranslation, true));
        static::assertSame('no html', $productTranslation['description']);
        static::assertSame('<p>html</p>', $productTranslation['description_long']);
        static::assertSame('Another Company', $productManufacturerTranslation['name']);
        static::assertSame('http://www.shopware.com', $productManufacturer['link']);
    }

    public function testUpdateWritesDefaultColumnsIfOmmitted(): void
    {
        $this->insertEmptyProduct();

        $newProduct = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                ['id' => $this->id, 'ean' => 'ABC'],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);

        static::assertSame('ABC', $product['ean']);

        static::assertNotEquals('0000-00-00 00:00:00', $product['updated_at']);
        static::assertNotEquals('2011-01-01 15:03:01', $product['updated_at']);

        static::assertNotEquals('0000-00-00 00:00:00', $product['created_at']);
        static::assertNotEquals('2011-01-01 15:03:01', $product['created_at']);
        static::assertNotEquals('0000-00-00 00:00:00', $newProduct['created_at']);
        static::assertNotEquals('2011-01-01 15:03:01', $newProduct['created_at']);
    }

    public function testInsertIgnoresDeferredFields(): void
    {
        static::assertNotNull(MediaDefinition::getFields()->get('url')->getFlag(Deferred::class));
        $id = '2b9a945bb62b4122a32a3bbfbe1e6fd3';
        $writeContext = $this->createWriteContext();
        $writeContext->getContext()->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->getWriter()->insert(
            MediaDefinition::class,
            [
                [
                    'id' => $id,
                    'name' => 'Test media',
                    'fileName' => 'testFile',
                    'mimeType' => 'image/jpeg',
                    'fileExtension' => 'jpg',
                    'url' => 'www.example.com',
                ],
            ],
            $writeContext
        );

        $media = $this->getMediaRepository()->search(
            new Criteria([$id]),
            Context::createDefaultContext()
        )->get($id);
        static::assertStringEndsWith('/testFile.jpg', $media->getUrl());
    }

    public function testUpdateIgnoresDeferredFields(): void
    {
        static::assertNotNull(MediaDefinition::getFields()->get('url')->getFlag(Deferred::class));
        $id = '2b9a945bb62b4122a32a3bbfbe1e6fd3';
        $writeContext = $this->createWriteContext();
        $writeContext->getContext()->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->getWriter()->insert(
            MediaDefinition::class,
            [
                [
                    'id' => $id,
                    'name' => 'Test media',
                    'fileName' => 'testFile',
                    'mimeType' => 'image/jpeg',
                    'fileExtension' => 'jpg',
                ],
            ],
            $writeContext
        );

        $this->getWriter()->update(
            MediaDefinition::class,
            [
                ['id' => $id, 'url' => 'www.example.com'],
            ],
            $this->createWriteContext()
        );

        $media = $this->getMediaRepository()->search(
            new Criteria([$id]),
            Context::createDefaultContext()
        )->get($id);
        static::assertStringEndsWith('/testFile.jpg', $media->getUrl());
    }

    public function testUpdateWritesMultipleTranslations(): void
    {
        $this->insertEmptyProduct();

        $localeId = Uuid::uuid4()->getHex();
        $this->getContainer()->get('locale.repository')->upsert([
            ['id' => $localeId, 'name' => 'test', 'territory' => 'tmp', 'code' => Uuid::uuid4()->getHex()],
        ], Context::createDefaultContext());

        $this->getContainer()->get('language.repository')->upsert([
            [
                'id' => '2d905256e75149678dd5a32a81b94f1f',
                'name' => 'language 2',
                'localeId' => $localeId,
                'localeVersionId' => Defaults::LIVE_VERSION,
                'translationCode' => [
                    'code' => 'x-tst_' . Uuid::uuid4()->getHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ],
        ], Context::createDefaultContext());

        $this->getWriter()->update(
            ProductDefinition::class,
            [
                [
                    'id' => $this->id,
                    'name' => [
                        Defaults::LANGUAGE_SYSTEM => '1ABC',
                        '2d905256e75149678dd5a32a81b94f1f' => '2ABC',
                    ],
                    'description' => 'foo', // implicit FFA32A50E2D04CF38389A53F8D6CD594
                    'descriptionLong' => [
                        '2d905256e75149678dd5a32a81b94f1f' => '2CBA',
                    ],
                    'translations' => [
                        '2d905256e75149678dd5a32a81b94f1f' => [
                            'name' => 'bar',
                            'description' => 'foo',
                            'keywords' => 'fiz,baz',
                        ],
                    ],
                    'metaTitle' => [
                        '2d905256e75149678dd5a32a81b94f1f' => 'bar',
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
//            'languageId' => '2d905256e75149678dd5a32a81b94f1f',
//            'metaTitle' => 'bar',
//            'name' => '',
//            [...]
//        ]
//
//        'POST /product/abc' => [];

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);
        $productTranslations = $this->connection->fetchAll('SELECT * FROM product_translation WHERE product_id= :id', ['id' => $this->idBytes]);

        static::assertNotEmpty($product);

        static::assertCount(2, $productTranslations, print_r($productTranslations, true));

        $productTranslations = array_map(function ($a) {
            $a['language_id'] = Uuid::fromBytesToHex($a['language_id']);

            return $a;
        }, $productTranslations);

        foreach ($productTranslations as $translation) {
            if ($translation['language_id'] === Defaults::LANGUAGE_SYSTEM) {
                static::assertSame('1ABC', $translation['name']);
                static::assertSame('foo', $translation['description']);
                static::assertNull($translation['description_long']);
                static::assertNull($translation['meta_title']);
                static::assertNull($translation['keywords']);
            } else {
                static::assertSame('2ABC', $translation['name']);
                static::assertSame('foo', $translation['description']);
                static::assertSame('2CBA', $translation['description_long']);
                static::assertSame('bar', $translation['meta_title']);
                static::assertSame('fiz,baz', $translation['keywords']);
            }
        }
    }

    public function testUpdateInvalid(): void
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

    public function testInsertWithOnlyRequiredTranslated()
    {
        $id = Uuid::uuid4()->getHex();
        $data = ['id' => $id];

        $this->expectException(WriteTypeIntendException::class);
        $this->getWriter()->update(
            TaxDefinition::class,
            [$data],
            WriteContext::createFromContext(Context::createDefaultContext())
        );
    }

    public function testWriteOneToManyWithOptionalIdField(): void
    {
        $mediaRepo = $this->getContainer()->get('media.repository');
        $mediaId = Uuid::uuid4()->getHex();
        $manufacturerId = Uuid::uuid4()->getHex();

        $mediaRepo->upsert([
            [
                'id' => $mediaId,
                'name' => 'media',
                'productManufacturers' => [
                    [
                        'id' => $manufacturerId,
                        'name' => 'test',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $manufacturer = $this->getContainer()->get('product_manufacturer.repository')
            ->search(new Criteria([$manufacturerId]), Context::createDefaultContext())
            ->get($manufacturerId);

        static::assertNotNull($manufacturer);
        static::assertEquals($mediaId, $manufacturer->getMediaId());
    }

    public function testWriteTranslatedEntityWithoutRequiredFieldsNotInSystemLanguage(): void
    {
        $mediaRepo = $this->getContainer()->get('media.repository');
        $mediaId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();
        $context = new Context(
            $context->getSourceContext(),
            $context->getRules(),
            $context->getCurrencyId(),
            [Defaults::LANGUAGE_SYSTEM_DE, Defaults::LANGUAGE_SYSTEM]
        );

        $mediaRepo->create([
            [
                'id' => $mediaId,
                'name' => 'media',
            ],
        ], $context);

        static::assertEquals(
            1,
            $mediaRepo->search(new Criteria([$mediaId]), $context)->getEntities()->count()
        );
    }

    /**
     * @return WriteContext
     */
    protected function createWriteContext(): WriteContext
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());

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
                    'price' => ['gross' => 10, 'net' => 8.10],
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => [
                        'id' => Uuid::uuid4()->getHex(),
                        'name' => 'shopware AG',
                        'link' => 'https://shopware.com',
                    ],
                    'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
                    'updated_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
                ],
            ],
            $this->createWriteContext()
        );
    }

    private function getWriter(): EntityWriterInterface
    {
        return $this->getContainer()->get(EntityWriter::class);
    }

    private function getMediaRepository(): EntityRepositoryInterface
    {
        return $this->getContainer()->get('media.repository');
    }

    private function getTaxRepository(): EntityRepository
    {
        return $this->getContainer()->get('tax.repository');
    }
}
