<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class SearchKeywordUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $productRepository;

    private EntityRepositoryInterface $searchKeywordRepository;

    private Connection $connection;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->searchKeywordRepository = $this->getContainer()->get('product_search_keyword.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @dataProvider productKeywordProvider
     */
    public function testItUpdatesKeywordsAndDictionary(array $productData, IdsCollection $ids, array $englishKeywords, array $germanKeywords, array $additionalDictionaries = []): void
    {
        $this->productRepository->create([$productData], $ids->getContext());

        $this->assertKeywords($ids->get('1000'), Defaults::LANGUAGE_SYSTEM, $englishKeywords);
        $this->assertKeywords($ids->get('1000'), $this->getDeDeLanguageId(), $germanKeywords);

        $expectedDictionary = array_merge($englishKeywords, $additionalDictionaries);
        sort($expectedDictionary);
        $this->assertDictionary(Defaults::LANGUAGE_SYSTEM, $expectedDictionary);
        $expectedDictionary = array_merge($germanKeywords, $additionalDictionaries);
        sort($expectedDictionary);
        $this->assertDictionary($this->getDeDeLanguageId(), $expectedDictionary);
    }

    public function productKeywordProvider(): array
    {
        $idsCollection = new IdsCollection();

        return [
            'test different languages' => [
                (new ProductBuilder($idsCollection, '1000'))
                    ->price(10)
                    ->name('Test product')
                    ->translation($this->getDeDeLanguageId(), 'name', 'Test produkt')
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
                    ->manufacturer('manufacturer', [$this->getDeDeLanguageId() => ['name' => 'Hersteller']])
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
                    ->translation($this->getDeDeLanguageId(), 'name', 'Test produkt')
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
                    ->manufacturer('manufacturer', [$this->getDeDeLanguageId() => ['name' => 'Hersteller']])
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
}
