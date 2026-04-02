<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Util\ExplicitProductIdResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

#[CoversClass(ExplicitProductIdResolver::class)]
class ExplicitProductIdResolverTest extends TestCase
{
    public function testFromCriteriaCollectsIdsFromSupportedFields(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('id', 'explicit-a'),
            new EqualsAnyFilter('product.id', ['explicit-b', 'explicit-c']),
            new EqualsFilter('productNumber', 'ignored'),
        ]));

        static::assertSame(['explicit-a', 'explicit-b', 'explicit-c'], ExplicitProductIdResolver::fromCriteria($criteria));
    }

    public function testFromFiltersIgnoresNotFiltersUnsupportedFieldsAndDuplicateIds(): void
    {
        $filters = [
            new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsFilter('id', 'ignored-by-not'),
            ]),
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('product.id', 'explicit-a'),
                new EqualsAnyFilter('id', ['explicit-a', 'explicit-b', 5]),
                new EqualsAnyFilter('manufacturerId', ['ignored']),
            ]),
        ];

        static::assertSame(['explicit-a', 'explicit-b'], ExplicitProductIdResolver::fromFilters($filters));
    }
}
