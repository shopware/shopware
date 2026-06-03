<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1775460999AddParentNameToProductSearchConfig;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
class SearchKeywordUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $salesChannelLanguageRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->salesChannelLanguageRepository = static::getContainer()->get('sales_channel_language.repository');
        $this->connection = static::getContainer()->get(Connection::class);

        // Guarantees a clean state for assertDictionary(), assertKeywords(), assertLanguageHasNoDictionary
        $this->connection->executeStatement('DELETE FROM product');
        $this->connection->executeStatement('DELETE FROM product_search_keyword');
        $this->connection->executeStatement('DELETE FROM product_keyword_dictionary');
    }

    /**
     * @param array<mixed> $productData
     * @param string[] $englishKeywords
     * @param string[] $germanKeywords
     * @param string[] $additionalDictionaries
     */
    #[DataProvider('productKeywordProvider')]
    public function testItUpdatesKeywordsAndDictionary(array $productData, IdsCollection $ids, array $englishKeywords, array $germanKeywords, array $additionalDictionaries = []): void
    {
        $this->productRepository->create([$productData], Context::createDefaultContext());

        $this->assertKeywords($ids->get('1000'), Defaults::LANGUAGE_SYSTEM, $englishKeywords);
        $this->assertKeywords($ids->get('1000'), $this->getDeDeLanguageId(), $germanKeywords);

        $expectedDictionary = array_merge($englishKeywords, $additionalDictionaries);
        sort($expectedDictionary);
        $this->assertDictionary(Defaults::LANGUAGE_SYSTEM, $expectedDictionary);
        $expectedDictionary = array_merge($germanKeywords, $additionalDictionaries);
        sort($expectedDictionary);
        $this->assertDictionary($this->getDeDeLanguageId(), $expectedDictionary);
    }

    /**
     * @param array<mixed> $productData
     * @param string[] $englishKeywords
     * @param string[] $germanKeywords
     * @param string[] $additionalDictionaries
     */
    #[DataProvider('productKeywordProvider')]
    public function testItUpdatesKeywordsForAvailableLanguagesOnly(array $productData, IdsCollection $ids, array $englishKeywords, array $germanKeywords, array $additionalDictionaries = []): void
    {
        $context = Context::createDefaultContext();

        /** @var Criteria<array<string, string>> $criteria */
        $criteria = new Criteria();

        // Delete sales channel de-DE language associations to ensure only default language is used to create keywords.
        $criteria->addFilter(new EqualsFilter('languageId', $this->getDeDeLanguageId()));

        $salesChannelLanguageIds = $this->salesChannelLanguageRepository->searchIds($criteria, $context)->getIds();
        $this->salesChannelLanguageRepository->delete($salesChannelLanguageIds, $context);

        $this->productRepository->create([$productData], Context::createDefaultContext());

        $this->assertKeywords($ids->get('1000'), Defaults::LANGUAGE_SYSTEM, $englishKeywords);

        $expectedDictionary = array_merge($englishKeywords, $additionalDictionaries);
        sort($expectedDictionary);
        $this->assertDictionary(Defaults::LANGUAGE_SYSTEM, $expectedDictionary);

        $this->assertLanguageHasNoKeywords($this->getDeDeLanguageId());
        $this->assertLanguageHasNoDictionary($this->getDeDeLanguageId());
    }

    public function testCustomFields(): void
    {
        $ids = new IdsCollection();
        $products = [
            (new ProductBuilder($ids, 'p1'))->price(100)->build(),
            (new ProductBuilder($ids, 'p2'))->price(100)->build(),
        ];

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        static::getContainer()->get('product.repository')
            ->create($products, $context);

        $id = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM product_search_config WHERE language_id = :id', ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);

        $fields = [
            ['searchConfigId' => $id, 'searchable' => true, 'field' => 'customFields.field1', 'tokenize' => true, 'ranking' => 100, 'language_id' => Defaults::LANGUAGE_SYSTEM],
            ['searchConfigId' => $id, 'searchable' => true, 'field' => 'manufacturer.customFields.field1', 'tokenize' => true, 'ranking' => 100, 'language_id' => Defaults::LANGUAGE_SYSTEM],
        ];

        static::getContainer()->get('product_search_config_field.repository')
            ->create($fields, Context::createDefaultContext());

        static::getContainer()->get(SearchKeywordUpdater::class)
            ->update($ids->getList(['p1', 'p2']), Context::createDefaultContext());

        // Products should still get keywords from the default searchable fields (name, productNumber)
        // even when custom fields are configured but have no values
        $this->assertKeywords($ids->get('p1'), Defaults::LANGUAGE_SYSTEM, ['p1']);
        $this->assertKeywords($ids->get('p2'), Defaults::LANGUAGE_SYSTEM, ['p2']);
    }

    /**
     * Tests that associations without a direct FK field (like ManyToMany) don't cause errors.
     * Categories is a ManyToMany association without a categoriesId FK field on product.
     * This test verifies that the buildCriteria method correctly handles associations
     * that don't have a corresponding FK field by not attempting to filter on non-existent fields.
     */
    public function testAssociationWithoutFkFieldDoesNotThrowError(): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        // Create product with category
        $products = [
            (new ProductBuilder($ids, 'p1'))
                ->price(100)
                ->name('Test product')
                ->categories(['testcategory'])
                ->build(),
        ];

        static::getContainer()->get('product.repository')->create($products, $context);

        // Enable categories.name as a searchable field (ManyToMany without FK field)
        $rowsAffected = $this->connection->executeStatement(
            'UPDATE product_search_config_field SET searchable = 1, tokenize = 1, ranking = 100
             WHERE field = :field',
            [
                'field' => 'categories.name',
            ]
        );

        static::assertGreaterThan(0, $rowsAffected, 'categories.name field should exist and be updated');

        static::getContainer()->get(SearchKeywordUpdater::class)->reset();

        // This should not throw an error even though 'categoriesId' FK field doesn't exist
        static::getContainer()->get(SearchKeywordUpdater::class)
            ->update([$ids->get('p1')], Context::createDefaultContext());

        // Basic keywords from product name and number should still be generated
        $this->assertKeywords($ids->get('p1'), Defaults::LANGUAGE_SYSTEM, [
            'p1',
            'product',
            'test',
            'test product',
        ]);
    }

    public function testItUpdatesVariantKeywordsWithParentNameWhenConfigured(): void
    {
        $ids = new IdsCollection();
        $languageRepository = static::getContainer()->get('language.repository');
        static::assertInstanceOf(EntityRepository::class, $languageRepository);

        $analyzer = static::getContainer()->get(ProductSearchKeywordAnalyzer::class);
        static::assertInstanceOf(ProductSearchKeywordAnalyzer::class, $analyzer);

        $searchKeywordUpdater = new SearchKeywordUpdater(
            $this->connection,
            $languageRepository,
            $this->productRepository,
            $analyzer,
            new MockClock()
        );

        $originalParentNameSearchState = $this->enableParentNameSearch();

        try {
            $this->productRepository->create([
                (new ProductBuilder($ids, 'parent-name-keyword'))
                    ->name('Parent Searchable')
                    ->price(10)
                    ->variant(
                        (new ProductBuilder($ids, 'child-name-keyword'))
                            ->name('Child Variant')
                            ->number('childnumber')
                            ->price(11)
                            ->build()
                    )
                    ->build(),
            ], Context::createDefaultContext());

            $searchKeywordUpdater->reset();
            $searchKeywordUpdater->update([
                $ids->get('child-name-keyword'),
            ], Context::createDefaultContext());

            $keywords = $this->connection->fetchFirstColumn(
                'SELECT `keyword`
                FROM `product_search_keyword`
                WHERE `product_id` = :productId AND language_id = :languageId
                ORDER BY `keyword` ASC',
                [
                    'productId' => Uuid::fromHexToBytes($ids->get('child-name-keyword')),
                    'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                ]
            );

            static::assertContains('parent', $keywords);
            static::assertContains('parent searchable', $keywords);
            static::assertContains('searchable', $keywords);
        } finally {
            $this->restoreParentNameSearch($originalParentNameSearchState);
            $searchKeywordUpdater->reset();
        }
    }

    public function testItSkipsKeywordGenerationForNotUsedLanguages(): void
    {
        $ids = new IdsCollection();
        $esLocale = $this->getLocaleIdByIsoCode('es-ES');

        $languageRepo = static::getContainer()->get('language.repository');
        $languageRepo->create([
            [
                'id' => $ids->get('language'),
                'name' => 'Español',
                'localeId' => $esLocale,
                'active' => true,
                'translationCodeId' => $esLocale,
            ],
        ], Context::createDefaultContext());

        $this->productRepository->create(
            [
                (new ProductBuilder($ids, '1000'))
                    ->price(10)
                    ->name('Test product')
                    ->translation($ids->get('language'), 'name', 'Test produkt')
                    ->build(),
            ],
            Context::createDefaultContext()
        );

        $this->assertKeywords(
            $ids->get('1000'),
            Defaults::LANGUAGE_SYSTEM,
            [
                '1000', // productNumber
                'product', // part of name
                'test', // part of name
                'test product', // product name
            ]
        );
        $this->assertKeywords($ids->get('1000'), $ids->get('language'), []);
    }

    /**
     * @return iterable<string, array<int, mixed>>
     */
    public static function productKeywordProvider(): iterable
    {
        $idsCollection = new IdsCollection();

        yield 'translated product name creates language specific keywords' => [
            (new ProductBuilder($idsCollection, '1000'))
                ->price(10)
                ->name('Test product')
                ->translation('de-DE', 'name', 'Test produkt')
                ->build(),
            $idsCollection,
            [
                '1000', // productNumber
                'product', // part of name
                'test', // part of name
                'test product', // product name
            ],
            [
                '1000', // productNumber
                'produkt', // part of name
                'test', // part of name
                'test produkt', // product name
            ],
        ];
        yield 'missing translation falls back to parent language keywords' => [
            (new ProductBuilder($idsCollection, '1000'))
                ->price(10)
                ->name('Test product')
                ->build(),
            $idsCollection,
            [
                '1000', // productNumber
                'product', // part of name
                'test', // part of name
                'test product', // product name
            ],
            [
                '1000', // productNumber
                'product', // part of name
                'test', // part of name
                'test product', // product name
            ],
        ];
        yield 'translated manufacturer name creates language specific keywords' => [
            (new ProductBuilder($idsCollection, '1000'))
                ->price(10)
                ->name('Test product')
                ->manufacturer('manufacturer', ['de-DE' => ['name' => 'Hersteller']])
                ->build(),
            $idsCollection,
            [
                '1000', // productNumber
                'manufacturer', // manufacturer name
                'product', // part of name
                'test', // part of name
                'test product', // product name
            ],
            [
                '1000', // productNumber
                'Hersteller', // manufacturer name
                'product', // part of name
                'test', // part of name
                'test product', // product name
            ],
        ];
        yield 'variant inherits translated product name from parent' => [
            (new ProductBuilder($idsCollection, '1001'))
                ->name('Test product')
                ->translation('de-DE', 'name', 'Test produkt')
                ->price(5)
                ->variant(
                    (new ProductBuilder($idsCollection, '1000'))
                        ->price(10)
                        ->name(null)
                        ->build()
                )
                ->build(),
            $idsCollection,
            [
                '1000', // productNumber
                'product', // part of name
                'test', // part of name
                'test product', // product name
            ],
            [
                '1000', // productNumber
                'produkt', // part of name
                'test', // part of name
                'test produkt', // product name
            ],
            ['1001'],
        ];
        yield 'variant inherits translated manufacturer name from parent' => [
            (new ProductBuilder($idsCollection, '1001'))
                ->name('Test product')
                ->manufacturer('manufacturer', ['de-DE' => ['name' => 'Hersteller']])
                ->price(5)
                ->variant(
                    (new ProductBuilder($idsCollection, '1000'))
                        ->price(10)
                        ->name(null)
                        ->build()
                )
                ->build(),
            $idsCollection,
            [
                '1000', // productNumber
                'manufacturer', // manufacturer name
                'product', // part of name
                'test', // part of name
                'test product', // product name
            ],
            [
                '1000', // productNumber
                'Hersteller', // manufacturer name
                'product', // part of name
                'test', // part of name
                'test product', // product name
            ],
            ['1001'],
        ];
    }

    public function testGetConfigFieldsFiltersCustomFieldsBySearchable(): void
    {
        $customFieldName = 'searchable_field';
        $this->createCustomFieldWithSearchConfig($customFieldName, active: true, searchable: true);

        $configFields = $this->queryConfigFieldsDirectly();
        $customFieldFields = array_filter($configFields, static fn ($field) => str_starts_with($field['field'] ?? '', 'customFields.' . $customFieldName));
        static::assertCount(1, $customFieldFields);

        $includedField = reset($customFieldFields);
        static::assertSame('customFields.' . $customFieldName, $includedField['field']);
    }

    /**
     * @param string[] $expectedKeywords
     */
    private function assertKeywords(string $productId, string $languageId, array $expectedKeywords): void
    {
        $keywords = $this->connection->fetchFirstColumn(
            'SELECT `keyword`
            FROM `product_search_keyword`
            WHERE `product_id` = :productId AND language_id = :languageId
            ORDER BY `keyword` ASC',
            [
                'productId' => Uuid::fromHexToBytes($productId),
                'languageId' => Uuid::fromHexToBytes($languageId),
            ]
        );

        static::assertEquals($expectedKeywords, $keywords, 'no match: ' . print_r($keywords, true));
    }

    private function assertLanguageHasNoKeywords(string $languageId): void
    {
        $keywords = $this->connection->fetchFirstColumn(
            'SELECT `keyword`
            FROM `product_search_keyword`
            WHERE language_id = :languageId
            ORDER BY `keyword` ASC',
            [
                'languageId' => Uuid::fromHexToBytes($languageId),
            ]
        );

        static::assertCount(0, $keywords);
    }

    /**
     * @param string[] $expectedKeywords
     */
    private function assertDictionary(string $languageId, array $expectedKeywords): void
    {
        $dictionary = $this->connection->fetchFirstColumn(
            'SELECT `keyword`
            FROM `product_keyword_dictionary`
            WHERE language_id = :languageId
            ORDER BY `keyword` ASC',
            [
                'languageId' => Uuid::fromHexToBytes($languageId),
            ]
        );

        static::assertSame($expectedKeywords, $dictionary);
    }

    private function assertLanguageHasNoDictionary(string $languageId): void
    {
        $dictionary = $this->connection->fetchFirstColumn(
            'SELECT `keyword`
            FROM `product_keyword_dictionary`
            WHERE language_id = :languageId
            ORDER BY `keyword` ASC',
            [
                'languageId' => Uuid::fromHexToBytes($languageId),
            ]
        );

        static::assertCount(0, $dictionary);
    }

    private function getLocaleIdByIsoCode(string $iso): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', $iso));

        $firstId = static::getContainer()->get('locale.repository')
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();

        static::assertIsString($firstId);

        return $firstId;
    }

    private function createCustomFieldWithSearchConfig(string $fieldName, bool $active = true, bool $searchable = true): string
    {
        $customFieldSetId = Uuid::randomHex();
        $this->connection->executeStatement(
            'INSERT INTO custom_field_set (id, name, config, active, created_at)
            VALUES (:id, :name, :config, 1, NOW())',
            [
                'id' => Uuid::fromHexToBytes($customFieldSetId),
                'name' => 'test_set',
                'config' => json_encode([]),
            ]
        );

        $this->connection->executeStatement(
            'INSERT INTO custom_field_set_relation (id, set_id, entity_name, created_at)
            VALUES (:id, :setId, :entityName, NOW())',
            [
                'id' => Uuid::randomBytes(),
                'setId' => Uuid::fromHexToBytes($customFieldSetId),
                'entityName' => 'product',
            ]
        );

        $customFieldId = Uuid::randomHex();
        $this->connection->executeStatement(
            'INSERT INTO custom_field (id, name, type, config, active, set_id, created_at, include_in_search)
            VALUES (:id, :name, :type, :config, :active, :setId, NOW(), :includeInSearch)',
            [
                'id' => Uuid::fromHexToBytes($customFieldId),
                'name' => $fieldName,
                'type' => 'text',
                'config' => json_encode([]),
                'active' => $active ? 1 : 0,
                'setId' => Uuid::fromHexToBytes($customFieldSetId),
                'includeInSearch' => $searchable ? 1 : 0,
            ]
        );

        $searchConfigId = $this->connection->fetchOne(
            'SELECT id FROM product_search_config WHERE language_id = :languageId',
            ['languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $this->connection->executeStatement(
            'INSERT INTO product_search_config_field (id, product_search_config_id, field, searchable, tokenize, ranking, custom_field_id, created_at)
            VALUES (:id, :configId, :field, 1, 0, 1000, :customFieldId, NOW())',
            [
                'id' => Uuid::randomBytes(),
                'configId' => $searchConfigId,
                'field' => 'customFields.' . $fieldName,
                'customFieldId' => Uuid::fromHexToBytes($customFieldId),
            ]
        );

        return $customFieldId;
    }

    /**
     * @return array<string, string>
     */
    private function enableParentNameSearch(): array
    {
        /** @var array<string, string> $originalState */
        $originalState = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), searchable FROM product_search_config_field WHERE field = :field',
            ['field' => 'parent.name']
        );

        (new Migration1775460999AddParentNameToProductSearchConfig())->update($this->connection);

        $this->connection->executeStatement(
            'UPDATE product_search_config_field SET searchable = 1 WHERE field = :field',
            ['field' => 'parent.name']
        );

        return $originalState;
    }

    /**
     * @param array<string, string> $originalState
     */
    private function restoreParentNameSearch(array $originalState): void
    {
        $parentNameConfigIds = array_map(
            'strval',
            $this->connection->fetchFirstColumn(
                'SELECT LOWER(HEX(id)) FROM product_search_config_field WHERE field = :field',
                ['field' => 'parent.name']
            )
        );

        $addedConfigIds = array_values(array_diff($parentNameConfigIds, array_keys($originalState)));
        if ($addedConfigIds !== []) {
            $this->connection->executeStatement(
                'DELETE FROM product_search_config_field WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($addedConfigIds)],
                ['ids' => ArrayParameterType::BINARY]
            );
        }

        foreach ($originalState as $id => $searchable) {
            $this->connection->executeStatement(
                'UPDATE product_search_config_field SET searchable = :searchable WHERE id = :id',
                [
                    'id' => Uuid::fromHexToBytes($id),
                    'searchable' => (int) $searchable,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function queryConfigFieldsDirectly(): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('configField.field', 'configField.tokenize', 'configField.ranking', 'LOWER(HEX(config.language_id)) as language_id');
        $query->from('product_search_config', 'config');
        $query->join('config', 'product_search_config_field', 'configField', 'config.id = configField.product_search_config_id');
        $query->andWhere('config.language_id IN (:languageIds)');
        $query->andWhere('configField.searchable = 1');

        $query->setParameter('languageIds', Uuid::fromHexToBytesList([Defaults::LANGUAGE_SYSTEM]), ArrayParameterType::BINARY);

        return $query->executeQuery()->fetchAllAssociative();
    }
}
