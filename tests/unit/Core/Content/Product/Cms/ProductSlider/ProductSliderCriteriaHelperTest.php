<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms\ProductSlider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Cms\ProductSlider\ProductSliderCriteriaHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductSliderCriteriaHelper::class)]
class ProductSliderCriteriaHelperTest extends TestCase
{
    public function testAddGrouping(): void
    {
        $criteria = new Criteria();
        ProductSliderCriteriaHelper::addGrouping($criteria);

        $groupFields = $criteria->getGroupFields();
        static::assertCount(1, $groupFields);

        $groupField = array_shift($groupFields);
        static::assertInstanceOf(FieldGrouping::class, $groupField);
        static::assertSame('displayGroup', $groupField->getField());

        $filters = $criteria->getFilters();
        static::assertCount(1, $filters);

        $filter = array_shift($filters);
        static::assertInstanceOf(NotFilter::class, $filter);
        static::assertSame(MultiFilter::CONNECTION_AND, $filter->getOperator());

        $queries = $filter->getQueries();
        static::assertCount(1, $queries);
        $query = array_shift($queries);

        static::assertInstanceOf(EqualsFilter::class, $query);
        static::assertSame('displayGroup', $query->getField());
    }

    public function testAddRandomSort(): void
    {
        $criteria = new Criteria();
        ProductSliderCriteriaHelper::addRandomSort($criteria);

        $sorting = $criteria->getSorting();
        static::assertContainsOnlyInstancesOf(FieldSorting::class, $sorting);
        static::assertCount(2, $sorting);
    }
}
