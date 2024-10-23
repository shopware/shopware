<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class SearchKeywordUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $productRepository;

    private EntityRepository $salesChannelLanguageRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->salesChannelLanguageRepository = $this->getContainer()->get('sales_channel_language.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
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

        $criteria = new Criteria();

        // Delete sales channel de-DE language associations to ensure only default language is used to create keywords.
        $criteria->addFilter(new EqualsFilter('languageId', $this->getDeDeLanguageId()));

        /** @var list<array<string, string>> $salesChannalLanguageIds */
        $salesChannalLanguageIds = $this->salesChannelLanguageRepository->searchIds($criteria, $context)->getIds();
        $this->salesChannelLanguageRepository->delete($salesChannalLanguageIds, $context);

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

        $this->getContainer()->get('product.repository')
            ->create($products, $context);

        $id = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM product_search_config WHERE language_id = :id', ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);

        $fields = [
            ['searchConfigId' => $id, 'searchable' => true, 'field' => 'customFields.field1', 'tokenize' => true, 'ranking' => 100, 'language_id' => Defaults::LANGUAGE_SYSTEM],
            ['searchConfigId' => $id, 'searchable' => true, 'field' => 'manufacturer.customFields.field1', 'tokenize' => true, 'ranking' => 100, 'language_id' => Defaults::LANGUAGE_SYSTEM],
        ];

        $this->getContainer()->get('product_search_config_field.repository')
            ->create($fields, Context::createDefaultContext());

        $this->getContainer()->get(SearchKeywordUpdater::class)
            ->update($ids->getList(['p1', 'p2']), Context::createDefaultContext());
    }

    public function testItSkipsKeywordGenerationForNotUsedLanguages(): void
    {
        $ids = new IdsCollection();
        $esLocale = $this->getLocaleIdByIsoCode('es-ES');

        $languageRepo = $this->getContainer()->get('language.repository');
        $languageRepo->create([
            [
                'id' => $ids->get('language'),
                'name' => 'Español',
                'localeId' => $esLocale,
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
            ]
        );
        $this->assertKeywords($ids->get('1000'), $ids->get('language'), []);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function productKeywordProvider(): array
    {
        $idsCollection = new IdsCollection();

        return [
            'test different languages' => [
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
                ],
                [
                    '1000', // productNumber
                    'produkt', // part of name
                    'test', // part of name
                ],
            ],
            'test it uses parent languages' => [
                (new ProductBuilder($idsCollection, '1000'))
                    ->price(10)
                    ->name('Test product')
                    ->build(),
                $idsCollection,
                [
                    '1000', // productNumber
                    'product', // part of name
                    'test', // part of name
                ],
                [
                    '1000', // productNumber
                    'product', // part of name
                    'test', // part of name
                ],
            ],
            'test it uses correct languages for association' => [
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
                ],
                [
                    '1000', // productNumber
                    'Hersteller', // manufacturer name
                    'product', // part of name
                    'test', // part of name
                ],
            ],
            'test it uses correct translation from parent' => [
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
                ],
                [
                    '1000', // productNumber
                    'produkt', // part of name
                    'test', // part of name
                ],
                ['1001'],
            ],
            'test it uses correct translation from parent association' => [
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
                ],
                [
                    '1000', // productNumber
                    'Hersteller', // manufacturer name
                    'product', // part of name
                    'test', // part of name
                ],
                ['1001'],
            ],
        ];
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

        static::assertEquals($expectedKeywords, $keywords);
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

        static::assertEquals($expectedKeywords, $dictionary);
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

        $firstId = $this->getContainer()->get('locale.repository')
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();

        static::assertIsString($firstId);

        return $firstId;
    }
}
