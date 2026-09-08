<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RepositoryIterator::class)]
class RepositoryIteratorTest extends TestCase
{
    public function testFetchUsesAutoIncrementCursorWhenCriteriaHasNoSorting(): void
    {
        $context = Context::createDefaultContext();
        $criteria = (new Criteria())->setLimit(2)->setOffset(9);
        $productA = $this->product(5);
        $productB = $this->product(9);
        $calls = 0;

        $search = function (Criteria $searchCriteria) use (&$calls, $context, $productA, $productB): EntitySearchResult {
            static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $searchCriteria->getTotalCountMode());
            static::assertSame(0, $searchCriteria->getOffset());
            static::assertSame('autoIncrement', $searchCriteria->getSorting()[0]->getField());

            $range = $searchCriteria->getFilters()['increment'] ?? null;
            static::assertInstanceOf(RangeFilter::class, $range);

            if ($calls === 0) {
                static::assertSame(0, $range->getParameter(RangeFilter::GTE));
                $entities = new ProductCollection([$productA, $productB]);
            } else {
                static::assertSame(9, $range->getParameter(RangeFilter::GT));
                $entities = new ProductCollection();
            }

            ++$calls;

            return new EntitySearchResult(ProductDefinition::ENTITY_NAME, $entities->count(), $entities, null, $searchCriteria, $context);
        };

        $repository = StaticEntityRepository::of(ProductCollection::class, [$search, $search], new ProductDefinition());

        $iterator = new RepositoryIterator($repository, $context, $criteria);

        static::assertNotNull($iterator->fetch());
        static::assertNull($iterator->fetch());
    }

    public function testFetchKeepsOffsetPaginationForCriteriaWithSorting(): void
    {
        $context = Context::createDefaultContext();
        $criteria = (new Criteria())->setLimit(2)->setOffset(3);
        $criteria->addSorting(new FieldSorting('name'));

        $repository = StaticEntityRepository::of(ProductCollection::class, [
            function (Criteria $searchCriteria) use ($context): EntitySearchResult {
                static::assertSame(3, $searchCriteria->getOffset());
                static::assertArrayNotHasKey('increment', $searchCriteria->getFilters());

                return new EntitySearchResult(ProductDefinition::ENTITY_NAME, 1, new ProductCollection([$this->product(1)]), null, $searchCriteria, $context);
            },
        ], new ProductDefinition());

        $iterator = new RepositoryIterator($repository, $context, $criteria);

        static::assertNotNull($iterator->fetch());
        static::assertSame(5, $criteria->getOffset());
    }

    public function testFetchAddsAutoIncrementCursorToPartialFields(): void
    {
        $criteria = (new Criteria())->setLimit(2);
        $criteria->addFields(['name']);

        $repository = StaticEntityRepository::of(ProductCollection::class, [], new ProductDefinition());

        new RepositoryIterator($repository, Context::createDefaultContext(), $criteria);

        static::assertSame(['name', 'autoIncrement'], $criteria->getFields());
    }

    public function testFetchIdsKeepsOffsetPaginationForCriteriaWithSorting(): void
    {
        $context = Context::createDefaultContext();
        $criteria = (new Criteria())->setLimit(2)->setOffset(3);
        $criteria->addSorting(new FieldSorting('name'));
        $id = Uuid::randomHex();

        $repository = StaticEntityRepository::of(ProductCollection::class, [
            function (Criteria $searchCriteria) use ($id): array {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $searchCriteria->getTotalCountMode());
                static::assertSame(3, $searchCriteria->getOffset());
                static::assertArrayNotHasKey('increment', $searchCriteria->getFilters());

                return [$id];
            },
        ], new ProductDefinition());

        $iterator = new RepositoryIterator($repository, $context, $criteria);

        static::assertSame([$id], $iterator->fetchIds());
        static::assertSame(5, $criteria->getOffset());
    }

    private function product(int $autoIncrement): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setAutoIncrement($autoIncrement);

        return $product;
    }
}
