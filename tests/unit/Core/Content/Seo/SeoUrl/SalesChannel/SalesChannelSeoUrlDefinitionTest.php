<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrl\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SalesChannel\SalesChannelSeoUrlDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(SalesChannelSeoUrlDefinition::class)]
#[Package('inventory')]
class SalesChannelSeoUrlDefinitionTest extends TestCase
{
    public function testProcessCriteriaAddsDefaultFilters(): void
    {
        $definition = new SalesChannelSeoUrlDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        $filters = $criteria->getFilters();
        static::assertNotEmpty($filters);

        // Check all expected default filters
        static::assertTrue($criteria->hasEqualsFilter('languageId'));
        static::assertTrue($criteria->hasEqualsFilter('isCanonical'));
        static::assertTrue($criteria->hasEqualsFilter('isDeleted'));

        // Verify default values
        $isCanonicalFilter = $this->findEqualsFilter($filters, 'isCanonical');
        static::assertNotNull($isCanonicalFilter);
        static::assertTrue($isCanonicalFilter->getValue(), 'Default isCanonical should be true');

        $isDeletedFilter = $this->findEqualsFilter($filters, 'isDeleted');
        static::assertNotNull($isDeletedFilter);
        static::assertFalse($isDeletedFilter->getValue(), 'Default isDeleted should be false');
    }

    /**
     * @return iterable<string, array{field: string, userValue: bool|null}>
     */
    public static function provideApiFilterOverrides(): iterable
    {
        yield 'isCanonical can be overridden to false' => [
            'field' => 'isCanonical',
            'userValue' => false,
        ];

        yield 'isCanonical can be overridden to null' => [
            'field' => 'isCanonical',
            'userValue' => null,
        ];

        yield 'isDeleted can be overridden to true' => [
            'field' => 'isDeleted',
            'userValue' => true,
        ];
    }

    /**
     * Tests with QueryStringParser to simulate real Store API behavior.
     * The API automatically prefixes field names with the entity name (e.g., "isCanonical" becomes "seo_url.isCanonical").
     */
    #[DataProvider('provideApiFilterOverrides')]
    public function testProcessCriteriaRespectsApiProvidedFilter(string $field, ?bool $userValue): void
    {
        $definition = new SalesChannelSeoUrlDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        // Simulate API request - QueryStringParser adds entity prefix to field names
        $filter = QueryStringParser::fromArray(
            $definition,
            ['type' => 'equals', 'field' => $field, 'value' => $userValue],
            new SearchRequestException()
        );

        $prefixedField = 'seo_url.' . $field;

        // Verify QueryStringParser added the prefix
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame($prefixedField, $filter->getField(), 'QueryStringParser should prefix field with entity name');

        $criteria->addFilter($filter);
        $definition->processCriteria($criteria, $context);

        $filters = $criteria->getFilters();

        // Verify no default filter was added for the base field
        $defaultFilters = array_filter(
            $filters,
            static fn ($f) => $f instanceof EqualsFilter && $f->getField() === $field
        );
        static::assertCount(0, $defaultFilters, "Default $field filter should not be added when API filter exists");

        // Verify user's prefixed filter is preserved with correct value
        $prefixedFilters = array_filter(
            $filters,
            static fn ($f) => $f instanceof EqualsFilter && $f->getField() === $prefixedField
        );
        static::assertCount(1, $prefixedFilters, 'User API filter should be preserved');

        $preservedFilter = reset($prefixedFilters);
        static::assertInstanceOf(EqualsFilter::class, $preservedFilter);
        static::assertSame($userValue, $preservedFilter->getValue(), "User-provided $field value should be preserved");
    }

    /**
     * Documents limitation: hasEqualsFilter() only checks top-level filters.
     * Filters nested in MultiFilter are not detected, so defaults are still added.
     */
    public function testProcessCriteriaAddsDefaultFilterWhenFilterIsNested(): void
    {
        $definition = new SalesChannelSeoUrlDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        // User provides isCanonical inside a MultiFilter (not a top-level EqualsFilter)
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('seo_url.isCanonical', false),
            new EqualsFilter('seo_url.isCanonical', null),
        ]));

        $definition->processCriteria($criteria, $context);

        // hasEqualsFilter() only checks top-level filters, so default is still added
        $topLevelFilters = array_filter(
            $criteria->getFilters(),
            static fn ($filter) => $filter instanceof EqualsFilter && $filter->getField() === 'isCanonical'
        );

        static::assertCount(1, $topLevelFilters, 'Default filter added when only nested filters exist');
    }

    /**
     * @param array<mixed> $filters
     */
    private function findEqualsFilter(array $filters, string $field): ?EqualsFilter
    {
        foreach ($filters as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === $field) {
                return $filter;
            }
        }

        return null;
    }
}
