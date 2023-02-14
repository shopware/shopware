<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class EntitySearchResultTest extends TestCase
{
    /**
     * @dataProvider resultPageCriteriaDataProvider
     */
    public function testResultPage(Criteria $criteria, int $page): void
    {
        $entity = new ArrayEntity(['id' => Uuid::randomHex()]);
        $entityCollection = new EntityCollection([$entity]);
        $result = new EntitySearchResult(
            ArrayEntity::class,
            100,
            $entityCollection,
            null,
            $criteria,
            Context::createDefaultContext()
        );

        static::assertSame($page, $result->getPage());
    }

    public function testSlice(): void
    {
        $entitySearchResult = $this->createEntitySearchResult();

        $newInstance = $entitySearchResult->slice(2);

        static::assertSame(ArrayEntity::class, $newInstance->getEntity());
        static::assertSame(ArrayEntity::class, $newInstance->first()::class);
        static::assertSame(8, $newInstance->getTotal());
        static::assertSame($entitySearchResult->getAggregations(), $newInstance->getAggregations());
        static::assertSame($entitySearchResult->getCriteria(), $newInstance->getCriteria());
        static::assertSame($entitySearchResult->getContext(), $newInstance->getContext());
    }

    public function testFilter(): void
    {
        $entitySearchResult = $this->createEntitySearchResult();

        $count = 0;

        $newInstance = $entitySearchResult->filter(function () use (&$count) {
            return $count++ > 5;
        });

        static::assertSame(ArrayEntity::class, $newInstance->getEntity());
        static::assertSame(ArrayEntity::class, $newInstance->first()::class);
        static::assertSame(4, $newInstance->getTotal());
        static::assertSame($entitySearchResult->getAggregations(), $newInstance->getAggregations());
        static::assertSame($entitySearchResult->getCriteria(), $newInstance->getCriteria());
        static::assertSame($entitySearchResult->getContext(), $newInstance->getContext());
    }

    public static function resultPageCriteriaDataProvider(): \Generator
    {
        yield [(new Criteria())->setLimit(5)->setOffset(0), 1];
        yield [(new Criteria())->setLimit(5)->setOffset(1), 1];
        yield [(new Criteria())->setLimit(5)->setOffset(9), 2];
        yield [(new Criteria())->setLimit(5)->setOffset(10), 3];
        yield [(new Criteria())->setLimit(5)->setOffset(11), 3];
        yield [(new Criteria())->setLimit(10)->setOffset(25), 3];
    }

    private function createEntitySearchResult(): EntitySearchResult
    {
        $entities = [];
        for ($i = 1; $i <= 10; ++$i) {
            $entities[] = new ArrayEntity(['id' => Uuid::randomHex()]);
        }
        $entityCollection = new EntityCollection($entities);

        return new EntitySearchResult(
            ArrayEntity::class,
            $entityCollection->count(),
            $entityCollection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
