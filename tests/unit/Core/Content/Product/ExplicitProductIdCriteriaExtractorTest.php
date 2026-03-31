<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ExplicitProductIdCriteriaExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
#[CoversClass(ExplicitProductIdCriteriaExtractor::class)]
class ExplicitProductIdCriteriaExtractorTest extends TestCase
{
    public function testExtractCollectsIdsFromCriteriaAndSupportedFilters(): void
    {
        $criteria = new Criteria(['criteria-id']);
        $criteria->addFilter(new EqualsFilter('id', 'filter-id'));
        $criteria->addFilter(new EqualsAnyFilter('product.id', ['variant-1', 'variant-2']));

        static::assertSame(
            [
                'criteria-id' => 'criteria-id',
                'filter-id' => 'filter-id',
                'variant-1' => 'variant-1',
                'variant-2' => 'variant-2',
            ],
            ExplicitProductIdCriteriaExtractor::extract($criteria)
        );
    }

    public function testExtractIgnoresUnsupportedAndNegatedFilters(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('product.id', 'included-id'),
            new EqualsFilter('manufacturerId', 'ignored-id'),
        ]));
        $criteria->addPostFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('id', 'excluded-id'),
        ]));

        static::assertSame(
            ['included-id' => 'included-id'],
            ExplicitProductIdCriteriaExtractor::extract($criteria)
        );
    }
}
