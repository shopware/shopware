<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\SalesChannelRepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelRepositoryIterator::class)]
class SalesChannelRepositoryIteratorTest extends TestCase
{
    public function testKeysetFetchSeeksPastCursorAndTracksLastId(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->method('getDefinition')->willReturn($this->productDefinition());

        $criteria = new Criteria();
        $criteria->setLimit(2);

        $productA = $this->product(5);
        $productB = $this->product(9);

        $calls = 0;
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$calls, $context, $productA, $productB): EntitySearchResult {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $criteria->getTotalCountMode());
                static::assertSame(0, $criteria->getOffset());

                $ranges = array_values(array_filter(
                    $criteria->getFilters(),
                    static fn ($filter) => $filter instanceof RangeFilter && $filter->getField() === 'autoIncrement'
                ));

                if ($calls === 0) {
                    static::assertSame([], $ranges, 'The first keyset page must not seek.');
                    $collection = new SalesChannelProductCollection([$productA, $productB]);
                } else {
                    static::assertCount(1, $ranges, 'Subsequent pages must seek past the last cursor.');
                    static::assertSame(9, $ranges[0]->getParameter(RangeFilter::GT));
                    $collection = new SalesChannelProductCollection();
                }

                ++$calls;

                return new EntitySearchResult('product', $collection->count(), $collection, null, $criteria, $context->getContext());
            });

        $iterator = new SalesChannelRepositoryIterator($repository, $context, $criteria);

        $first = $iterator->fetch();
        static::assertNotNull($first);
        static::assertCount(2, $first->getEntities());
        static::assertSame(9, $iterator->getOffset());

        // Empty page terminates iteration; cursor stays at the last seen value.
        static::assertNull($iterator->fetch());
        static::assertSame(9, $iterator->getOffset());
    }

    public function testKeysetResumesFromProvidedCursor(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->method('getDefinition')->willReturn($this->productDefinition());

        $criteria = new Criteria();
        $criteria->setLimit(2);

        $repository->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use ($context): EntitySearchResult {
                $ranges = array_values(array_filter(
                    $criteria->getFilters(),
                    static fn ($filter) => $filter instanceof RangeFilter && $filter->getField() === 'autoIncrement'
                ));
                static::assertCount(1, $ranges, 'A resumed iterator must seek past the provided cursor.');
                static::assertSame(41, $ranges[0]->getParameter(RangeFilter::GT));

                $collection = new SalesChannelProductCollection();

                return new EntitySearchResult('product', 0, $collection, null, $criteria, $context->getContext());
            });

        $iterator = new SalesChannelRepositoryIterator($repository, $context, $criteria, 41);

        static::assertNull($iterator->fetch());
        static::assertSame(41, $iterator->getOffset());
    }

    public function testCriteriaWithOwnSortingKeepsOffsetPagination(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->method('getDefinition')->willReturn($this->productDefinition());

        $criteria = new Criteria();
        $criteria->setLimit(2);
        $criteria->addSorting(new FieldSorting('name'));

        $repository->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use ($context): EntitySearchResult {
                $ranges = array_filter(
                    $criteria->getFilters(),
                    static fn ($filter) => $filter instanceof RangeFilter && $filter->getField() === 'autoIncrement'
                );
                static::assertSame([], $ranges, 'A criteria with its own sorting must not switch to keyset.');

                $collection = new SalesChannelProductCollection([$this->product(1)]);

                return new EntitySearchResult('product', 1, $collection, null, $criteria, $context->getContext());
            });

        $iterator = new SalesChannelRepositoryIterator($repository, $context, $criteria);

        static::assertNotNull($iterator->fetch());
        static::assertSame(2, $iterator->getOffset(), 'Offset mode resumes via a plain row offset.');
    }

    private function productDefinition(): ProductDefinition
    {
        // ProductDefinition has an AutoIncrementField; hasAutoIncrement() is final and cannot be mocked.
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class, ProductCategoryDefinition::class, ProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->get(ProductDefinition::class);
        static::assertInstanceOf(ProductDefinition::class, $definition);

        return $definition;
    }

    private function product(int $autoIncrement): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));
        $product->setId(Uuid::randomHex());
        $product->setAutoIncrement($autoIncrement);

        return $product;
    }
}
