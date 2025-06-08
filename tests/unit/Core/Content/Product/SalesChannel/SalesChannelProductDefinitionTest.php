<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(SalesChannelProductDefinition::class)]
class SalesChannelProductDefinitionTest extends TestCase
{
    public function testProcessCriteriaRootLevel(): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        static::assertNotEmpty($criteria->getAssociations());
        static::assertTrue($criteria->hasAssociation('prices'));
        static::assertTrue($criteria->hasAssociation('unit'));
        static::assertTrue($criteria->hasAssociation('deliveryTime'));
        static::assertTrue($criteria->hasAssociation('cover'));

        static::assertNotEmpty($criteria->getFilters());
    }

    public function testProcessCriteriaAssociationLevel(): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria(nestingLevel: 1);
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        static::assertEmpty($criteria->getAssociations());

        static::assertNotEmpty($criteria->getFilters());
    }

    public function testProcessCriteriaWithVisibilityFilter(): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.visibilities.salesChannelId', 'sales-channel-id'));
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        // Verify that no ProductAvailableFilter was added when visibility filter is present
        $filters = $criteria->getFilters();
        $hasVisibilityFilter = false;
        $hasProductAvailableFilter = false;

        foreach ($filters as $filter) {
            if (in_array('product.visibilities.salesChannelId', $filter->getFields(), true)) {
                $hasVisibilityFilter = true;
            }
            if ($filter instanceof ProductAvailableFilter) {
                $hasProductAvailableFilter = true;
            }
        }

        static::assertTrue($hasVisibilityFilter);
        static::assertFalse($hasProductAvailableFilter);
    }

    public function testProcessCriteriaWithoutVisibilityFilter(): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        // Verify that ProductAvailableFilter was added when no visibility filter is present
        $filters = $criteria->getFilters();
        $hasProductAvailableFilter = false;

        foreach ($filters as $filter) {
            if ($filter instanceof ProductAvailableFilter) {
                $hasProductAvailableFilter = true;
            }
        }

        static::assertTrue($hasProductAvailableFilter);
    }

    public function testProcessCriteriaWithExistingAvailableFilter(): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria();
        $criteria->addFilter(new ProductAvailableFilter('existing-sales-channel-id'));
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        // Verify that no additional ProductAvailableFilter was added when one already exists
        $filters = $criteria->getFilters();
        $availableFilterCount = 0;

        foreach ($filters as $filter) {
            if ($filter instanceof ProductAvailableFilter) {
                $availableFilterCount++;
            }
        }

        static::assertSame(1, $availableFilterCount);
    }
}
