<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingResult::class)]
class ProductListingResultTest extends TestCase
{
    public function testFromSearchResultCopiesResultProperties(): void
    {
        $source = $this->createSearchResult();

        $listing = ProductListingResult::fromSearchResult($source);

        static::assertSame($source->getTotal(), $listing->getTotal());
        static::assertSame($source->getEntities(), $listing->getEntities());
        static::assertSame($source->getCriteria(), $listing->getCriteria());
        static::assertSame($source->getContext(), $listing->getContext());
    }

    public function testFromSearchResultSetsListingSpecificFields(): void
    {
        $sortings = new ProductSortingCollection();

        $listing = ProductListingResult::fromSearchResult(
            $this->createSearchResult(),
            availableSortings: $sortings,
            sorting: 'name-asc',
            currentFilters: ['category' => 'electronics'],
            streamId: 'stream-id-1',
        );

        static::assertSame($sortings, $listing->getAvailableSortings());
        static::assertSame('name-asc', $listing->getSorting());
        static::assertSame(['category' => 'electronics'], $listing->getCurrentFilters());
        static::assertSame('stream-id-1', $listing->getStreamId());
    }

    public function testFromSearchResultUsesDefaultsWhenExtrasOmitted(): void
    {
        $listing = ProductListingResult::fromSearchResult($this->createSearchResult());

        static::assertNull($listing->getSorting());
        static::assertSame([], $listing->getCurrentFilters());
        static::assertNull($listing->getStreamId());
    }

    public function testFromSearchResultKeepsPaginationAggregationsExtensionsAndStates(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(20);

        $source = new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            42,
            new ProductCollection(),
            new AggregationResultCollection(),
            $criteria,
            Context::createDefaultContext(),
        );
        $source->addExtension('custom', new ArrayStruct(['foo' => 'bar']));
        $source->addState('custom-state');

        $listing = ProductListingResult::fromSearchResult($source);

        static::assertSame(10, $listing->getLimit());
        static::assertSame(3, $listing->getPage());
        static::assertSame($source->getAggregations(), $listing->getAggregations());
        static::assertSame($source->getExtension('custom'), $listing->getExtension('custom'));
        static::assertTrue($listing->hasState('custom-state'));
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private function createSearchResult(): EntitySearchResult
    {
        $entities = new ProductCollection();

        return new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
