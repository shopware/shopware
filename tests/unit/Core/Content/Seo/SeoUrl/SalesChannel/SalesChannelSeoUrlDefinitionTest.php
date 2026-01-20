<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrl\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SalesChannel\SalesChannelSeoUrlDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
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
     * @return iterable<string, array{field: string, userValue: bool, expectedValue: bool}>
     */
    public static function provideOverridableFilters(): iterable
    {
        yield 'isCanonical can be overridden to false' => [
            'field' => 'isCanonical',
            'userValue' => false,
            'expectedValue' => false,
        ];

        yield 'isDeleted can be overridden to true' => [
            'field' => 'isDeleted',
            'userValue' => true,
            'expectedValue' => true,
        ];
    }

    #[DataProvider('provideOverridableFilters')]
    public function testProcessCriteriaRespectsUserProvidedFilter(string $field, bool $userValue, bool $expectedValue): void
    {
        $definition = new SalesChannelSeoUrlDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        $criteria->addFilter(new EqualsFilter($field, $userValue));

        $definition->processCriteria($criteria, $context);

        $filters = $criteria->getFilters();

        // Count filters for this field - should only be one (the user-provided one)
        $fieldFilters = array_filter(
            $filters,
            static fn ($filter) => $filter instanceof EqualsFilter && $filter->getField() === $field
        );

        static::assertCount(1, $fieldFilters, "Should only have one $field filter");

        $filter = reset($fieldFilters);
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame($expectedValue, $filter->getValue(), "User-provided $field filter should be preserved");
    }

    public function testProcessCriteriaAddsDefaultFilterWhenFilterIsNested(): void
    {
        $definition = new SalesChannelSeoUrlDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        // User provides isCanonical inside a MultiFilter (not a top-level EqualsFilter)
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('isCanonical', false),
            new EqualsFilter('isCanonical', null),
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
