<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\StaticEntityRepositoryVarAnnotationFixture;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
class StaticEntityRepositoryVarAnnotationCases extends TestCase
{
    /**
     * @var StaticEntityRepository<ProductCollection>
     */
    private StaticEntityRepository $property;

    public function testRedundantAnnotationOnATypedSearch(): void
    {
        /** @var StaticEntityRepository<ProductCollection> $repository */
        $repository = new StaticEntityRepository([new ProductCollection([new ProductEntity()])]);

        static::assertCount(1, $repository->search(new Criteria(), Context::createDefaultContext()));
    }

    public function testRedundantAnnotationOnASearchResult(): void
    {
        $result = new EntitySearchResult('product', 0, new ProductCollection(), null, new Criteria(), Context::createDefaultContext());

        /** @var StaticEntityRepository<ProductCollection> */
        $repository = new StaticEntityRepository([$result]);

        static::assertCount(0, $repository->search(new Criteria(), Context::createDefaultContext()));
    }

    public function testAnnotationOnEmptySearches(): void
    {
        /** @var StaticEntityRepository<ProductCollection> $repository */
        $repository = new StaticEntityRepository([]);

        static::assertSame([], $repository->upserts);
    }

    public function testAnnotationOnAnIdList(): void
    {
        /** @var StaticEntityRepository<ProductCollection> $repository */
        $repository = new StaticEntityRepository([['product-id']]);

        static::assertCount(1, $repository->searchIds(new Criteria(), Context::createDefaultContext())->getIds());
    }

    public function testAnnotationOnAGenericCollection(): void
    {
        /** @var StaticEntityRepository<ProductCollection> $repository */
        $repository = new StaticEntityRepository([new EntityCollection([new ProductEntity()])]);

        static::assertCount(1, $repository->search(new Criteria(), Context::createDefaultContext()));
    }

    public function testBoundWithTheFactory(): void
    {
        $repository = StaticEntityRepository::of(ProductCollection::class, [['product-id']]);

        static::assertCount(1, $repository->searchIds(new Criteria(), Context::createDefaultContext())->getIds());
    }

    public function testInferredFromTheSearch(): void
    {
        $repository = new StaticEntityRepository([new ProductCollection()]);

        static::assertCount(0, $repository->search(new Criteria(), Context::createDefaultContext()));
    }

    public function testEntityCollectionItselfIsLeftAlone(): void
    {
        /** @var StaticEntityRepository<EntityCollection<Entity>> $repository */
        $repository = new StaticEntityRepository([]);

        static::assertSame([], $repository->upserts);
    }

    public function testPropertyDeclarationKeepsItsType(): void
    {
        $this->property = new StaticEntityRepository([]);

        static::assertSame([], $this->property->upserts);
    }

    public function testRedundantAnnotationOnAReturnedConstruction(): void
    {
        static::assertCount(0, $this->typedRepository()->search(new Criteria(), Context::createDefaultContext()));
    }

    public function testAnnotationOnAReturnedConstructionWithUntypedSearches(): void
    {
        static::assertSame([], $this->untypedRepository([])->upserts);
    }

    /**
     * @return StaticEntityRepository<ProductCollection>
     */
    private function typedRepository(): StaticEntityRepository
    {
        /** @var StaticEntityRepository<ProductCollection> */
        return new StaticEntityRepository([new ProductCollection()]);
    }

    /**
     * @param array<mixed> $searches
     *
     * @return StaticEntityRepository<ProductCollection>
     */
    private function untypedRepository(array $searches): StaticEntityRepository
    {
        /** @var StaticEntityRepository<ProductCollection> */
        return new StaticEntityRepository($searches);
    }
}
