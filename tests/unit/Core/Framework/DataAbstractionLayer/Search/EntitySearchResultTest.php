<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntitySearchResult::class)]
class EntitySearchResultTest extends TestCase
{
    #[DataProvider('resultPageCriteriaDataProvider')]
    public function testResultPage(Criteria $criteria, int $page): void
    {
        $entity = new ArrayEntity(['id' => Uuid::randomHex()]);
        $entityCollection = new EntityCollection([$entity]);
        $result = new EntitySearchResult(
            'array',
            100,
            $entityCollection,
            null,
            $criteria,
            Context::createDefaultContext()
        );

        static::assertSame($page, $result->getPage());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSlice(): void
    {
        $entitySearchResult = $this->createEntitySearchResult();

        $newInstance = $entitySearchResult->slice(2);

        $firstInstance = $newInstance->first();
        static::assertNotNull($firstInstance);
        static::assertSame('array', $newInstance->getEntity());
        static::assertSame(ArrayEntity::class, $firstInstance::class);
        static::assertSame(8, $newInstance->getTotal());
        static::assertSame($entitySearchResult->getAggregations(), $newInstance->getAggregations());
        static::assertSame($entitySearchResult->getCriteria(), $newInstance->getCriteria());
        static::assertSame($entitySearchResult->getContext(), $newInstance->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFilter(): void
    {
        $entitySearchResult = $this->createEntitySearchResult();

        $count = 0;

        $newInstance = $entitySearchResult->filter(static function () use (&$count) {
            return $count++ > 5;
        });

        $firstInstance = $newInstance->first();
        static::assertNotNull($firstInstance);
        static::assertSame('array', $newInstance->getEntity());
        static::assertSame(ArrayEntity::class, $firstInstance::class);
        static::assertSame(4, $newInstance->getTotal());
        static::assertSame($entitySearchResult->getAggregations(), $newInstance->getAggregations());
        static::assertSame($entitySearchResult->getCriteria(), $newInstance->getCriteria());
        static::assertSame($entitySearchResult->getContext(), $newInstance->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testMergeAcceptsPlainEntityCollection(): void
    {
        $existingEntity = new ArrayEntity(['id' => Uuid::randomHex()]);
        $additionalEntity = new ArrayEntity(['id' => Uuid::randomHex()]);
        $entityCollection = new EntityCollection([$existingEntity]);

        $entitySearchResult = new EntitySearchResult(
            'array',
            $entityCollection->count(),
            $entityCollection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $entitySearchResult->merge(new EntityCollection([$existingEntity, $additionalEntity]));

        static::assertSame([$existingEntity, $additionalEntity], array_values($entitySearchResult->getElements()));
        static::assertSame([$existingEntity, $additionalEntity], array_values($entitySearchResult->getEntities()->getElements()));
    }

    public static function resultPageCriteriaDataProvider(): \Generator
    {
        // Criteria, Page
        yield [(new Criteria())->setLimit(5)->setOffset(0), 1];
        yield [(new Criteria())->setLimit(5)->setOffset(1), 1];
        yield [(new Criteria())->setLimit(5)->setOffset(9), 2];
        yield [(new Criteria())->setLimit(5)->setOffset(10), 3];
        yield [(new Criteria())->setLimit(5)->setOffset(11), 3];
        yield [(new Criteria())->setLimit(10)->setOffset(25), 3];
    }

    /**
     * @return EntitySearchResult<EntityCollection<ArrayEntity>>
     */
    private function createEntitySearchResult(): EntitySearchResult
    {
        $entities = [];
        for ($i = 1; $i <= 10; ++$i) {
            $entities[] = new ArrayEntity(['id' => Uuid::randomHex()]);
        }
        $entityCollection = new EntityCollection($entities);

        return new EntitySearchResult(
            'array',
            $entityCollection->count(),
            $entityCollection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
