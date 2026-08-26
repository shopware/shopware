<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelRepository::class)]
class SalesChannelRepositoryTest extends TestCase
{
    public function testSearchIdsIsRoutedThroughTheInstrumentor(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        $repo->searchIds(new Criteria(), $this->salesChannelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH_IDS], $operations);
    }

    public function testAggregateIsRoutedThroughTheInstrumentor(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        $repo->aggregate(new Criteria(), $this->salesChannelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_AGGREGATE], $operations);
    }

    public function testReadOnlySearchIsRoutedThroughTheInstrumentor(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        // an empty criteria needs no id lookup, so only the outer search operation is measured
        $repo->search(new Criteria(), $this->salesChannelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    public function testSearchWithIdLookupDoesNotEmitANestedSearchIds(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        $criteria = new Criteria();
        $criteria->setTerm('foo');
        $repo->search($criteria, $this->salesChannelContext());

        // the id lookup runs through the private doSearch(); the outer search is the only measured operation,
        // matching EntityRepository so the same query is not double-counted by a nested searchIds sample
        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    public function testSearchWithAggregationsDoesNotEmitANestedAggregate(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        $criteria = new Criteria();
        $criteria->addAggregation(new CountAggregation('agg', 'id'));
        $repo->search($criteria, $this->salesChannelContext());

        // the aggregate is a nested sub-operation (profiled, not metered); only the outer search is measured
        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    /**
     * @param list<string> $operations
     *
     * @return SalesChannelRepository<EntityCollection<Entity>>
     */
    private function repository(array &$operations): SalesChannelRepository
    {
        /** @var SalesChannelRepository<EntityCollection<Entity>> $repository */
        $repository = new SalesChannelRepository(
            new ProductDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
            $this->recordingInstrumentor($operations),
        );

        return $repository;
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }

    /**
     * An instrumentor stub that records each measured operation and transparently runs the wrapped
     * callback, so tests can assert the repository delegates to it.
     *
     * @param list<string> $operations
     */
    private function recordingInstrumentor(array &$operations): DalSearchInstrumentor
    {
        $instrumentor = static::createStub(DalSearchInstrumentor::class);
        $instrumentor->method('measure')->willReturnCallback(
            function (string $operation, EntityDefinition $definition, Criteria $criteria, \Closure $callback) use (&$operations): mixed {
                $operations[] = $operation;

                return $callback();
            }
        );

        return $instrumentor;
    }
}
