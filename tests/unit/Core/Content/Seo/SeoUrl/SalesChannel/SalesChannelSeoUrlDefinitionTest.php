<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrl\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
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
    public function testProcessCriteriaAddsDefaultIsCanonicalFilter(): void
    {
        $definition = new SalesChannelSeoUrlDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        $filters = $criteria->getFilters();
        static::assertNotEmpty($filters);

        // Find the isCanonical filter
        $isCanonicalFilter = null;
        foreach ($filters as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'isCanonical') {
                $isCanonicalFilter = $filter;
                break;
            }
        }

        static::assertNotNull($isCanonicalFilter, 'isCanonical filter should be present');
        static::assertTrue($isCanonicalFilter->getValue(), 'Default isCanonical filter should be true');
    }

    public function testProcessCriteriaRespectsUserProvidedIsCanonicalFilter(): void
    {
        $definition = new SalesChannelSeoUrlDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        // User provides isCanonical = false filter
        $criteria->addFilter(new EqualsFilter('isCanonical', false));

        $definition->processCriteria($criteria, $context);

        $filters = $criteria->getFilters();

        // Count isCanonical filters - should only be one (the user-provided one)
        $isCanonicalFilters = array_filter(
            $filters,
            static fn ($filter) => $filter instanceof EqualsFilter && $filter->getField() === 'isCanonical'
        );

        static::assertCount(1, $isCanonicalFilters, 'Should only have one isCanonical filter');

        $isCanonicalFilter = reset($isCanonicalFilters);
        static::assertInstanceOf(EqualsFilter::class, $isCanonicalFilter);
        static::assertFalse($isCanonicalFilter->getValue(), 'User-provided isCanonical filter should be preserved as false');
    }

    public function testProcessCriteriaAlwaysAppliesOtherDefaultFilters(): void
    {
        $definition = new SalesChannelSeoUrlDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        // User provides isCanonical filter
        $criteria->addFilter(new EqualsFilter('isCanonical', false));

        $definition->processCriteria($criteria, $context);

        $filters = $criteria->getFilters();

        // Check languageId filter is present
        $languageIdFilter = null;
        foreach ($filters as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'languageId') {
                $languageIdFilter = $filter;
                break;
            }
        }
        static::assertNotNull($languageIdFilter, 'languageId filter should be present');
        static::assertSame($context->getLanguageId(), $languageIdFilter->getValue());

        // Check salesChannelId MultiFilter is present
        $salesChannelMultiFilter = null;
        foreach ($filters as $filter) {
            if ($filter instanceof MultiFilter) {
                $salesChannelMultiFilter = $filter;
                break;
            }
        }
        static::assertNotNull($salesChannelMultiFilter, 'salesChannelId MultiFilter should be present');

        // Check isDeleted filter is present
        $isDeletedFilter = null;
        foreach ($filters as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'isDeleted') {
                $isDeletedFilter = $filter;
                break;
            }
        }
        static::assertNotNull($isDeletedFilter, 'isDeleted filter should be present');
        static::assertFalse($isDeletedFilter->getValue());
    }

    public function testProcessCriteriaAddsDefaultFilterWhenIsCanonicalInNestedMultiFilter(): void
    {
        $definition = new SalesChannelSeoUrlDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        // User provides isCanonical inside a MultiFilter (not a top-level EqualsFilter)
        // This simulates: filter[0][type]=multi&filter[0][operator]=or&filter[0][queries][0][type]=equals&filter[0][queries][0][field]=isCanonical&filter[0][queries][0][value]=false
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('isCanonical', false),
            new EqualsFilter('isCanonical', null),
        ]));

        $definition->processCriteria($criteria, $context);

        $filters = $criteria->getFilters();

        // The hasEqualsFilter() only checks top-level EqualsFilters, not nested ones
        // So the default isCanonical=true filter will still be added
        $topLevelIsCanonicalFilters = array_filter(
            $filters,
            static fn ($filter) => $filter instanceof EqualsFilter && $filter->getField() === 'isCanonical'
        );

        // Default filter is added because nested EqualsFilter is not detected
        static::assertCount(1, $topLevelIsCanonicalFilters, 'Default isCanonical filter should be added when only nested filters exist');

        $defaultFilter = reset($topLevelIsCanonicalFilters);
        static::assertInstanceOf(EqualsFilter::class, $defaultFilter);
        static::assertTrue($defaultFilter->getValue(), 'Default isCanonical filter should be true');
    }
}
