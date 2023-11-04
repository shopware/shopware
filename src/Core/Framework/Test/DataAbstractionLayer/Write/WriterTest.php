<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\IncompletePrimaryKeyException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField\NonUuidFkFieldSerializer;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField\TestEntityOneDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField\TestEntityTwoDefinition;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;

/**
 * @internal
 */
class WriterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $id;

    private Connection $connection;

    private string $idBytes;

    protected function setUp(): void
    {
        $this->id = Uuid::randomHex();
        $this->idBytes = Uuid::fromHexToBytes($this->id);

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testDelete(): void
    {
        $id = Uuid::randomHex();

        $context = $this->createWriteContext();

        $this->getWriter()->insert(
            $this->getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id, 'name' => 'test-country'],
            ],
            $context
        );

        $exists = $this->connection->fetchAllAssociative('SELECT * FROM category WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertNotEmpty($exists);

        $deleteResult = $this->getWriter()->delete(
            $this->getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id],
            ],
            $context
        );

        $exists = $this->connection->fetchAllAssociative('SELECT * FROM category WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertEmpty($exists);
        static::assertEmpty($deleteResult->getNotFound());
        static::assertNotEmpty($deleteResult->getDeleted());
    }

    public function testMultiDelete(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $context = $this->createWriteContext();

        $this->getWriter()->insert(
            $this->getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id, 'name' => 'test-country1'],
                ['id' => $id2, 'name' => 'test-country2'],
            ],
            $context
        );

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::STRING]
        );

        static::assertCount(2, $categories);

        $translations = $this->connection->fetchAllAssociative(
            'SELECT * FROM category_translation WHERE category_id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::STRING]
        );

        static::assertCount(2, $translations);

        $deleteResult = $this->getWriter()->delete(
            $this->getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id],
                ['id' => $id2],
            ],
            $context
        );
        static::assertEmpty($deleteResult->getNotFound());
        static::assertNotEmpty($deleteResult->getDeleted()[CategoryDefinition::ENTITY_NAME]);

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::STRING]
        );

        static::assertEmpty($categories);

        $translations = $this->connection->fetchAllAssociative(
            'SELECT * FROM category_translation WHERE category_id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::STRING]
        );

        static::assertEmpty($translations);
    }

    public function testMultiDeleteWithNoneExistingId(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $context = $this->createWriteContext();

        $this->getWriter()->insert(
            $this->getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id, 'name' => 'test-country1'],
                ['id' => $id2, 'name' => 'test-country2'],
            ],
            $context
        );

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::STRING]
        );

        static::assertCount(2, $exists);

        $deleteResult = $this->getWriter()->delete(
            $this->getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id],
                ['id' => $id2],
                ['id' => Uuid::randomHex()],
                ['id' => Uuid::randomHex()],
                ['id' => Uuid::randomHex()],
            ],
            $context
        );

        static::assertCount(3, $deleteResult->getNotFound()[CategoryDefinition::ENTITY_NAME]);
        static::assertCount(2, $deleteResult->getDeleted()[CategoryDefinition::ENTITY_NAME]);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::STRING]
        );

        static::assertEmpty($exists);
    }

    public function testDeleteWithMultiplePrimaryColumns(): void
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $context = $this->createWriteContext();
        $this->getWriter()->insert($this->getContainer()->get(ProductDefinition::class), [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_category WHERE product_id = :product AND category_id = :category',
            ['product' => Uuid::fromHexToBytes($productId), 'category' => Uuid::fromHexToBytes($categoryId)]
        );
        static::assertCount(1, $exists);

        $deleteResult = $this->getWriter()->delete($this->getContainer()->get(ProductCategoryDefinition::class), [
            ['productId' => $productId, 'categoryId' => $categoryId],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_category WHERE product_id = :product AND category_id = :category',
            ['product' => Uuid::fromHexToBytes($productId), 'category' => Uuid::fromHexToBytes($categoryId)]
        );
        static::assertEmpty($exists);

        static::assertCount(1, $deleteResult->getDeleted()[ProductCategoryDefinition::ENTITY_NAME]);
        static::assertCount(0, $deleteResult->getNotFound());
    }

    public function testRequiresAllPrimaryKeyValuesForDelete(): void
    {
        $this->expectException(IncompletePrimaryKeyException::class);

        $productId = Uuid::randomHex();

        $this->getWriter()->delete($this->getContainer()->get(ProductCategoryDefinition::class), [
            ['productId' => $productId],
        ], $this->createWriteContext());
    }

    public function testMultiDeleteWithMultiplePrimaryColumns(): void
    {
        $productId = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $context = $this->createWriteContext();
        $this->getWriter()->insert($this->getContainer()->get(ProductDefinition::class), [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test 1',
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],
            [
                'id' => $productId2,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test 1',
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId],
                ],
            ],
            [
                'productNumber' => Uuid::randomHex(),
                'name' => 'test 1',
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['name' => 'test'],
                ],
            ],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_category WHERE product_id IN (:product) AND category_id = :category',
            ['product' => [Uuid::fromHexToBytes($productId), Uuid::fromHexToBytes($productId2)], 'category' => Uuid::fromHexToBytes($categoryId)],
            ['product' => ArrayParameterType::STRING]
        );
        static::assertCount(2, $exists);

        $deleteResult = $this->getWriter()->delete($this->getContainer()->get(ProductCategoryDefinition::class), [
            ['productId' => $productId, 'categoryId' => $categoryId],
            ['productId' => $productId2, 'categoryId' => $categoryId],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_category WHERE product_id IN (:product) AND category_id = :category',
            ['product' => [Uuid::fromHexToBytes($productId), Uuid::fromHexToBytes($productId2)], 'category' => Uuid::fromHexToBytes($categoryId)],
            ['product' => ArrayParameterType::STRING]
        );
        static::assertEmpty($exists);

        static::assertCount(2, $deleteResult->getDeleted()[ProductCategoryDefinition::ENTITY_NAME]);
        static::assertCount(0, $deleteResult->getNotFound());
    }

    public function testInsertWithId(): void
    {
        $this->getWriter()->insert(
            $this->getContainer()->get(ProductDefinition::class),
            [
                [
                    'id' => $this->id,
                    'productNumber' => Uuid::randomHex(),
                    'name' => 'test',
                    'stock' => 1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                    'the_unknown_field' => 'do nothing?',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['id' => Uuid::randomHex(), 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
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

        $product = $this->connection->fetchAssociative('SELECT * FROM product WHERE id=:id', [
            'id' => $this->idBytes,
        ]);
        static::assertIsArray($product);

        static::assertIsArray($product);
        static::assertNotEmpty($product['id']);
    }

    public function testInsertWithoutId(): void
    {
        $productCountBefore = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product');

        $this->getWriter()->insert(
            $this->getContainer()->get(ProductDefinition::class),
            [
                [
                    'productNumber' => Uuid::randomHex(),
                    'the_unknown_field' => 'do nothing?',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'name' => 'foo',
                    'stock' => 1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                    'manufacturer' => ['id' => Uuid::randomHex(), 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
                ],
            ],
            $this->createWriteContext()
        );

        $productCountAfter = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product');

        static::assertSame($productCountBefore + 1, $productCountAfter);
    }

    public function testInsertFromDocs(): void
    {
        $this->getWriter()->insert(
            $this->getContainer()->get(ProductDefinition::class),
            [
                [
                    'id' => $this->id,
                    'productNumber' => Uuid::randomHex(),
                    'name' => 'ConfiguratorTest',
                    'description' => 'A test article',
                    'stock' => 1,
                    'descriptionLong' => '<p>I\'m a <b>test article</b></p>',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['id' => Uuid::randomHex(), 'link' => 'https://shopware.com', 'name' => 'shopware AG'],
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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                ],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssociative('SELECT * FROM product WHERE id=:id', [
            'id' => $this->idBytes,
        ]);

        static::assertNotEmpty($product);
    }

    public function testUpdate(): void
    {
        $this->insertEmptyProduct();

        $productManufacturerId = Uuid::randomHex();

        $this->getWriter()->update(
            $this->getContainer()->get(ProductDefinition::class),
            [
                [
                    'id' => $this->id,
                    'name' => '_THE_TITLE_',
                    'stock' => 1,
                    'the_unknown_field' => 'do nothing?',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'description' => '<p>html</p>',
                    'availableFrom' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'availableTo' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                    'manufacturer' => [
                        'id' => $productManufacturerId,
                        'link' => 'http://www.shopware.com',
                        'name' => 'Another Company',
                    ],
                ],
            ],
            $this->createWriteContext()
        );

        $productManufacturer = $this->connection->fetchAssociative('SELECT * FROM product_manufacturer WHERE id=:id', ['id' => Uuid::fromHexToBytes($productManufacturerId)]);
        static::assertIsArray($productManufacturer);
        $productManufacturerTranslation = $this->connection->fetchAssociative('SELECT * FROM product_manufacturer_translation WHERE product_manufacturer_id=:id', ['id' => Uuid::fromHexToBytes($productManufacturerId)]);
        static::assertIsArray($productManufacturerTranslation);
        $productTranslation = $this->connection->fetchAssociative('SELECT * FROM product_translation WHERE product_id=:id', ['id' => $this->idBytes]);
        static::assertIsArray($productTranslation);

        static::assertSame('_THE_TITLE_', $productTranslation['name'], print_r($productTranslation, true));
        static::assertSame('<p>html</p>', $productTranslation['description']);
        static::assertSame('Another Company', $productManufacturerTranslation['name']);
        static::assertSame('http://www.shopware.com', $productManufacturer['link']);
    }

    public function testUpdateWritesDefaultColumnsIfOmmitted(): void
    {
        $this->insertEmptyProduct();

        $newProduct = $this->connection->fetchAssociative('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);
        static::assertIsArray($newProduct);

        $this->getWriter()->update(
            $this->getContainer()->get(ProductDefinition::class),
            [
                ['id' => $this->id, 'ean' => 'ABC'],
            ],
            $this->createWriteContext()
        );

        $product = $this->connection->fetchAssociative('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);
        static::assertIsArray($product);

        static::assertSame('ABC', $product['ean']);

        static::assertNotEquals('0000-00-00 00:00:00', $product['updated_at']);
        static::assertNotEquals('2011-01-01 15:03:01', $product['updated_at']);

        static::assertNotEquals('0000-00-00 00:00:00', $product['created_at']);
        static::assertNotEquals('2011-01-01 15:03:01', $product['created_at']);
        static::assertNotEquals('0000-00-00 00:00:00', $newProduct['created_at']);
        static::assertNotEquals('2011-01-01 15:03:01', $newProduct['created_at']);
    }

    public function testInsertIgnoresRuntimeFields(): void
    {
        static::assertNotNull($this->getContainer()->get(MediaDefinition::class)->getFields()->get('url')->getFlag(Runtime::class));
        $id = '2b9a945bb62b4122a32a3bbfbe1e6fd3';
        $writeContext = $this->createWriteContext();
        $this->getWriter()->insert(
            $this->getContainer()->get(MediaDefinition::class),
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

    public function testUpdateIgnoresRuntimeFields(): void
    {
        static::assertNotNull($this->getContainer()->get(MediaDefinition::class)->getFields()->get('url')->getFlag(Runtime::class));
        $id = '2b9a945bb62b4122a32a3bbfbe1e6fd3';
        $writeContext = $this->createWriteContext();
        $this->getWriter()->insert(
            $this->getContainer()->get(MediaDefinition::class),
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
            $this->getContainer()->get(MediaDefinition::class),
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

        $localeId = Uuid::randomHex();
        $this->getContainer()->get('locale.repository')->upsert([
            ['id' => $localeId, 'name' => 'test', 'territory' => 'tmp', 'code' => Uuid::randomHex()],
        ], Context::createDefaultContext());

        $this->getContainer()->get('language.repository')->upsert([
            [
                'id' => '2d905256e75149678dd5a32a81b94f1f',
                'name' => 'language 2',
                'localeId' => $localeId,
                'localeVersionId' => Defaults::LIVE_VERSION,
                'translationCode' => [
                    'code' => 'x-tst_' . Uuid::randomHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ],
        ], Context::createDefaultContext());

        $this->getWriter()->update(
            $this->getContainer()->get(ProductDefinition::class),
            [
                [
                    'id' => $this->id,
                    'stock' => 1,
                    'name' => [
                        Defaults::LANGUAGE_SYSTEM => '1ABC',
                        '2d905256e75149678dd5a32a81b94f1f' => '2ABC',
                    ],
                    'description' => 'foo', // implicit FFA32A50E2D04CF38389A53F8D6CD594
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

        $product = $this->connection->fetchAssociative('SELECT * FROM product WHERE id=:id', ['id' => $this->idBytes]);
        static::assertIsArray($product);

        $productTranslations = $this->connection->fetchAllAssociative('SELECT * FROM product_translation WHERE product_id= :id', ['id' => $this->idBytes]);

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
                static::assertNull($translation['meta_title']);
                static::assertNull($translation['keywords']);
            } else {
                static::assertSame('bar', $translation['name']);
                static::assertSame('foo', $translation['description']);
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

        $this->expectException(WriteException::class);
        $this->getWriter()->update(
            $this->getContainer()->get(ProductDefinition::class),
            [
                ['id' => $this->id, 'name' => $tooLongValue],
            ],
            $this->createWriteContext()
        );
    }

    public function testInsertWithOnlyRequiredTranslated(): void
    {
        $id = Uuid::randomHex();
        $data = ['id' => $id];

        $this->expectException(WriteTypeIntendException::class);
        $this->getWriter()->update(
            $this->getContainer()->get(TaxDefinition::class),
            [$data],
            WriteContext::createFromContext(Context::createDefaultContext())
        );
    }

    public function testWriteOneToManyWithOptionalIdField(): void
    {
        $mediaRepo = $this->getContainer()->get('media.repository');
        $mediaId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();

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
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $context = new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]
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

    public function testWriteWithEmptyDataIsValid(): void
    {
        $productRepository = $this->getContainer()->get('product.repository');

        $context = Context::createDefaultContext();

        $exceptionThrown = false;

        try {
            $productRepository->create([], $context);
            $productRepository->upsert([], $context);
            $productRepository->update([], $context);
        } catch (\InvalidArgumentException) {
            $exceptionThrown = true;
        }

        static::assertFalse($exceptionThrown);
    }

    public function testCanWriteReadAndDeleteEntitiesWithFKFieldValuesThatAreNotUuids(): void
    {
        // Because this test creates new database tables we need to commit the current transaction. Because table
        // creation auto-commits the current transaction on database level and would cause errors when Doctrine tries to
        // commit the still-open transaction.
        $this->connection->commit();
        $container = $this->getContainer();
        $context = Context::createDefaultContext();
        /** @var DefinitionInstanceRegistry $definitionInstanceRegistry */
        $definitionInstanceRegistry = $container->get(DefinitionInstanceRegistry::class);

        // Prepare test entity 1 that has a non-uuid primary key
        $this->connection->executeStatement(
            'CREATE TABLE `test_entity_one` (
                `technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
        $definitionInstanceRegistry->register(new TestEntityOneDefinition());
        $testEntityOneRepository = new EntityRepository(
            $definitionInstanceRegistry->getByClassOrEntityName(TestEntityOneDefinition::class),
            $container->get(EntityReaderInterface::class),
            $container->get(VersionManager::class),
            $container->get(EntitySearcherInterface::class),
            $container->get(EntityAggregatorInterface::class),
            $container->get('event_dispatcher'),
            $container->get(EntityLoadedEventFactory::class)
        );

        // Prepare test entity 2 that references test entity 1
        $this->connection->executeStatement(
            'CREATE TABLE `test_entity_two` (
                `id` BINARY(16) NOT NULL,
                `test_entity_one_technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
        $definitionInstanceRegistry->register(new TestEntityTwoDefinition());
        $testEntityTwoRepository = new EntityRepository(
            $definitionInstanceRegistry->getByClassOrEntityName(TestEntityTwoDefinition::class),
            $container->get(EntityReaderInterface::class),
            $container->get(VersionManager::class),
            $container->get(EntitySearcherInterface::class),
            $container->get(EntityAggregatorInterface::class),
            $container->get('event_dispatcher'),
            $container->get(EntityLoadedEventFactory::class)
        );
        $container->set(NonUuidFkFieldSerializer::class, new NonUuidFkFieldSerializer());

        // Test creation
        $testEntityOneRepository->create(
            [
                [
                    'technicalName' => 'Some-Technical-Name',
                ],
            ],
            $context,
        );
        $testEntityTwoId = Uuid::randomHex();
        $testEntityTwoRepository->create(
            [
                [
                    'id' => $testEntityTwoId,
                    'testEntityOneTechnicalName' => 'Some-Technical-Name',
                ],
            ],
            $context,
        );

        // Test fetch
        $fetchedEntityOne = $testEntityOneRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'Some-Technical-Name')),
            $context,
        );
        static::assertNotNull($fetchedEntityOne);
        $fetchedEntityTwo = $testEntityTwoRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('id', $testEntityTwoId))
                ->addAssociation('testEntityOne'),
            $context,
        );
        static::assertNotNull($fetchedEntityTwo);

        // Test deletion
        $testEntityOneRepository->delete([['technicalName' => 'Some-Technical-Name']], $context);
        $testEntityTwoRepository->delete([['id' => $testEntityTwoId]], $context);

        // Clean up
        $this->connection->executeStatement(
            'DROP TABLE `test_entity_two`;
            DROP TABLE `test_entity_one`;',
        );
        $this->connection->beginTransaction();
    }

    public function testCanUpdateEntitiesToAddCustomFields(): void
    {
        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productId = Uuid::randomHex();

        $productRepository->create(
            [
                [
                    'id' => $productId,
                    'name' => 'foo',
                    'productNumber' => Uuid::randomHex(),
                    'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                    'stock' => 0,
                ],
            ],
            Context::createDefaultContext(),
        );

        $productRepository->update(
            [
                [
                    'id' => $productId,
                    'customFields' => ['foo' => 'bar'],
                ],
            ],
            Context::createDefaultContext(),
        );
    }

    public function testCloneVariantTranslation(): void
    {
        $ids = new IdsCollection();

        $this->getContainer()->get('language.repository')->create(
            [
                [
                    'id' => $ids->create('language'),
                    'name' => 'test-language',
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'translationCode' => [
                        'code' => Uuid::randomHex(),
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        $product = (new ProductBuilder($ids, 'parent'))
            ->price(100)
            ->variant(
                (new ProductBuilder($ids, 'child'))
                    ->translation($ids->get('language'), 'description', 'update')
                    ->build()
            )
            ->build();

        $this->getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $behavior = new CloneBehavior(['productNumber' => 'new-parent'], false);

        $this->getContainer()->get('product.repository')->clone(
            $ids->get('parent'),
            Context::createDefaultContext(),
            $ids->get('new-parent'),
            $behavior
        );

        $behavior = new CloneBehavior([
            'parentId' => $ids->get('new-parent'),
            'productNumber' => 'new-child',
        ], false);

        $this->getContainer()->get('product.repository')->clone(
            $ids->get('child'),
            Context::createDefaultContext(),
            $ids->get('new-child'),
            $behavior
        );

        $translations = $this->getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT name, description FROM product_translation WHERE language_id = :language AND product_id = :id',
            ['language' => $ids->getBytes('language'), 'id' => $ids->getBytes('new-child')]
        );
        static::assertIsArray($translations);

        static::assertIsArray($translations);
        static::assertNull($translations['name']);
        static::assertEquals('update', $translations['description']);
    }

    protected function createWriteContext(): WriteContext
    {
        return WriteContext::createFromContext(Context::createDefaultContext());
    }

    protected function insertEmptyProduct(): void
    {
        $this->getWriter()->insert(
            $this->getContainer()->get(ProductDefinition::class),
            [
                [
                    'id' => $this->id,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'Test product',
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'shopware AG',
                        'link' => 'https://shopware.com',
                    ],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ],
            $this->createWriteContext()
        );
    }

    private function getWriter(): EntityWriterInterface
    {
        return $this->getContainer()->get(EntityWriter::class);
    }

    private function getMediaRepository(): EntityRepository
    {
        return $this->getContainer()->get('media.repository');
    }
}
