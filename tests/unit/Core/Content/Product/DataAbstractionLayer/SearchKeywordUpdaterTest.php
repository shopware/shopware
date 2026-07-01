<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(SearchKeywordUpdater::class)]
class SearchKeywordUpdaterTest extends TestCase
{
    public function testBuildCriteriaFiltersTranslationsByLanguageChain(): void
    {
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([], new ProductDefinition());
        $updater = new SearchKeywordUpdater(
            $this->createMock(Connection::class),
            $this->createMock(EntityRepository::class),
            $productRepository,
            $this->createMock(ProductSearchKeywordAnalyzerInterface::class)
        );

        $chain = [Uuid::randomHex(), Uuid::randomHex(), Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);

        $criteria = new Criteria();
        $method = new \ReflectionMethod($updater, 'buildCriteria');
        // no searchable association accessors: this isolates the translation language filters,
        // which are the product fields the fix changes, without resolving association definitions
        $method->invoke($updater, [], $criteria, $context);

        $translationFilters = [];
        foreach ($this->flattenFilters($criteria->getFilters()) as $filter) {
            if ($filter instanceof EqualsAnyFilter
                && \in_array($filter->getField(), ['translations.languageId', 'parent.translations.languageId'], true)) {
                $translationFilters[$filter->getField()] = $filter->getValue();
            }
        }

        // both the product and the parent translations must be matched against the full
        // language inheritance chain, not just the current sales channel language
        static::assertArrayHasKey('translations.languageId', $translationFilters);
        static::assertArrayHasKey('parent.translations.languageId', $translationFilters);
        static::assertSame($chain, $translationFilters['translations.languageId']);
        static::assertSame($chain, $translationFilters['parent.translations.languageId']);
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
