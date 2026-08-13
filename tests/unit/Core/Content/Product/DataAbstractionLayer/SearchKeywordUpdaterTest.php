<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeywordCollection;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SearchKeywordUpdater::class)]
class SearchKeywordUpdaterTest extends TestCase
{
    public function testDisabledIndexingSkipsUpdate(): void
    {
        $languageRepository = $this->createMock(EntityRepository::class);
        $productRepository = $this->createMock(EntityRepository::class);
        $analyzer = $this->createMock(ProductSearchKeywordAnalyzerInterface::class);

        $languageRepository->expects($this->never())->method('search');
        $productRepository->expects($this->never())->method('search');
        $analyzer->expects($this->never())->method('analyze');

        $updater = new SearchKeywordUpdater(
            static::createStub(Connection::class),
            $languageRepository,
            $productRepository,
            $analyzer,
            new MockClock(),
            false
        );

        $updater->update(['f70db8f6eb884b1ea2a691da3f74dc93'], Context::createDefaultContext());
    }

    public function testUpdateHydratesParentNameForVariants(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $standaloneId = Uuid::randomHex();

        $child = $this->createProduct($childId, $parentId);
        $standalone = $this->createProduct($standaloneId, null);

        $parent = $this->createProduct($parentId, null);
        $parent->setName('Parent product');
        $parent->setTranslated(['name' => 'Parent product']);

        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([
            // products to index, the second (empty) search stops the iterator
            new ProductCollection([$child, $standalone]),
            new ProductCollection(),
            // parent products, hydrated for the `parent.name` config field
            new ProductCollection([$parent]),
            new ProductCollection(),
        ], $this->createProductDefinition());

        $analyzedProducts = [];
        $analyzer = $this->createMock(ProductSearchKeywordAnalyzerInterface::class);
        $analyzer->expects($this->exactly(2))
            ->method('analyze')
            ->willReturnCallback(function (ProductEntity $product) use (&$analyzedProducts): AnalyzedKeywordCollection {
                $analyzedProducts[$product->getId()] = $product;

                return new AnalyzedKeywordCollection();
            });

        $updater = $this->createUpdater(
            $productRepository,
            $this->createConnection([$this->createConfigField('parent.name')]),
            $analyzer
        );

        $updater->update([$childId, $standaloneId], Context::createDefaultContext());

        // the analyzer must see the variant with its parent name available for indexing
        static::assertArrayHasKey($childId, $analyzedProducts);
        $analyzedChild = $analyzedProducts[$childId]->getParent();
        static::assertInstanceOf(ProductEntity::class, $analyzedChild);
        static::assertSame('Parent product', $analyzedChild->getName());

        // products without a parent are left untouched
        static::assertArrayHasKey($standaloneId, $analyzedProducts);
        static::assertNull($analyzedProducts[$standaloneId]->getParent());
    }

    public function testUpdateDoesNotLoadParentProductsWhenFieldIsNotConfigured(): void
    {
        $childId = Uuid::randomHex();
        $child = $this->createProduct($childId, Uuid::randomHex());

        // exactly the two searches of the product iterator: loading parent products would exhaust the repository and let it throw
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([
            new ProductCollection([$child]),
            new ProductCollection(),
        ], $this->createProductDefinition());

        $updater = $this->createUpdater(
            $productRepository,
            $this->createConnection([
                $this->createConfigField('name'),
                $this->createConfigField('description'),
            ])
        );

        $updater->update([$childId], Context::createDefaultContext());

        static::assertNull($child->getParent());
    }

    public function testUpdateFiltersTranslationsByLanguageChain(): void
    {
        $languageId = Uuid::randomHex();
        $parentLanguageId = Uuid::randomHex();

        $criteria = null;
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([
            function (Criteria $searchCriteria) use (&$criteria): ProductCollection {
                $criteria = $searchCriteria;

                return new ProductCollection();
            },
        ], $this->createProductDefinition());

        $updater = $this->createUpdater(
            $productRepository,
            languageRepository: new StaticEntityRepository([
                new LanguageCollection([$this->createLanguage($languageId, $parentLanguageId)]),
            ])
        );

        $updater->update([Uuid::randomHex()], Context::createDefaultContext());

        static::assertInstanceOf(Criteria::class, $criteria);

        $translationFilters = [];
        foreach ($this->flattenFilters($criteria->getFilters()) as $filter) {
            if ($filter instanceof EqualsAnyFilter
                && \in_array($filter->getField(), ['translations.languageId', 'parent.translations.languageId'], true)) {
                $translationFilters[$filter->getField()] = $filter->getValue();
            }
        }

        // both the product and the parent translations must be matched against the full
        // language inheritance chain, not just the current sales channel language
        $chain = [$languageId, $parentLanguageId, Defaults::LANGUAGE_SYSTEM];
        static::assertArrayHasKey('translations.languageId', $translationFilters);
        static::assertArrayHasKey('parent.translations.languageId', $translationFilters);
        static::assertSame($chain, $translationFilters['translations.languageId']);
        static::assertSame($chain, $translationFilters['parent.translations.languageId']);
    }

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<LanguageCollection>|null $languageRepository
     */
    private function createUpdater(
        EntityRepository $productRepository,
        ?Connection $connection = null,
        ?ProductSearchKeywordAnalyzerInterface $analyzer = null,
        ?EntityRepository $languageRepository = null,
    ): SearchKeywordUpdater {
        if ($analyzer === null) {
            $analyzer = static::createStub(ProductSearchKeywordAnalyzerInterface::class);
            $analyzer->method('analyze')->willReturn(new AnalyzedKeywordCollection());
        }

        return new SearchKeywordUpdater(
            $connection ?? $this->createConnection(),
            $languageRepository ?? new StaticEntityRepository([
                new LanguageCollection([$this->createLanguage(Defaults::LANGUAGE_SYSTEM)]),
            ]),
            $productRepository,
            $analyzer,
            new MockClock()
        );
    }

    /**
     * The searchable fields are configured in `product_search_config_field`, so the
     * config rows have to be provided through the connection.
     *
     * @param list<array{field: string, tokenize: '1'|'0', ranking: numeric-string, language_id: string}> $configFields
     */
    private function createConnection(array $configFields = []): Connection&Stub
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn($configFields);

        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }

    /**
     * @return array{field: string, tokenize: '1'|'0', ranking: numeric-string, language_id: string}
     */
    private function createConfigField(string $field, string $languageId = Defaults::LANGUAGE_SYSTEM): array
    {
        return [
            'field' => $field,
            'tokenize' => '1',
            'ranking' => '500',
            'language_id' => $languageId,
        ];
    }

    /**
     * The searchable fields are resolved through the product definition, so the translation
     * definition has to be registered as well to resolve accessors like `name`.
     */
    private function createProductDefinition(): ProductDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class, ProductTranslationDefinition::class],
            Validation::createValidator(),
            new StaticEntityWriterGateway()
        );

        $definition = $registry->getByEntityName(ProductDefinition::ENTITY_NAME);
        static::assertInstanceOf(ProductDefinition::class, $definition);

        return $definition;
    }

    private function createLanguage(string $id, ?string $parentId = null): LanguageEntity
    {
        $language = new LanguageEntity();
        $language->setId($id);
        $language->setUniqueIdentifier($id);
        $language->setParentId($parentId);

        return $language;
    }

    private function createProduct(string $id, ?string $parentId): ProductEntity
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier($id);
        $product->setId($id);
        $product->setAutoIncrement(1);
        $product->setParentId($parentId);

        return $product;
    }

    /**
     * @param Filter[] $filters
     *
     * @return Filter[]
     */
    private function flattenFilters(array $filters): array
    {
        $result = [];
        foreach ($filters as $filter) {
            $result[] = $filter;
            if ($filter instanceof MultiFilter) {
                $result = array_merge($result, $this->flattenFilters($filter->getQueries()));
            }
        }

        return $result;
    }
}
