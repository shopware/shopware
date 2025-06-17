<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Validation;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 *
 * @extends SalesChannelRepository<TEntityCollection>
 *
 * @phpstan-type ResultTypes EntitySearchResult<TEntityCollection>|AggregationResultCollection|mixed|TEntityCollection|IdSearchResult|array
 */
class StaticSalesChannelRepository extends SalesChannelRepository
{
    /**
     * @param array<callable(Criteria, Context): (ResultTypes)|ResultTypes> $searches
     */
    public function __construct(
        private array $searches = [],
        private readonly ?EntityDefinition $definition = null,
    ) {
        if ($definition === null) {
            return;
        }

        try {
            $definition->getFields();
        } catch (\Throwable) {
            $registry = new StaticDefinitionInstanceRegistry(
                [$definition],
                Validation::createValidator(),
                new StaticEntityWriterGateway()
            );
            $definition->compile($registry);
        }
    }

    public function search(Criteria $criteria, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        $result = \array_shift($this->searches);
        $callable = $result;

        if (\is_callable($callable)) {
            $result = $callable($criteria, $salesChannelContext, $this);
        }

        if ($result instanceof EntitySearchResult) {
            return $result;
        }

        if (\is_array($result)) {
            $result = new EntityCollection($result);
        }

        if ($result instanceof EntityCollection) {
            /** @var TEntityCollection $result */
            return new EntitySearchResult(
                $this->getDummyEntityName(),
                $result->count(),
                $result,
                null,
                $criteria,
                $salesChannelContext->getContext(),
            );
        }

        if ($result instanceof AggregationResultCollection) {
            /** @var TEntityCollection $collection */
            $collection = new EntityCollection();

            return new EntitySearchResult(
                $this->getDummyEntityName(),
                0,
                $collection,
                $result,
                $criteria,
                $salesChannelContext->getContext(),
            );
        }

        throw new \RuntimeException('Invalid mock repository configuration');
    }

    public function searchIds(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        $result = \array_shift($this->searches);
        $callable = $result;

        if (\is_callable($callable)) {
            $result = $callable($criteria, $salesChannelContext);
        }

        if ($result instanceof IdSearchResult) {
            return $result;
        }

        if (!\is_array($result)) {
            throw new \RuntimeException('Invalid mock repository configuration');
        }

        // flat array of ids
        if (\array_key_exists(0, $result) && \is_string($result[0])) {
            $result = \array_map(fn (string $id) => ['primaryKey' => $id, 'data' => []], $result);
        }

        return new IdSearchResult(\count($result), $result, $criteria, $salesChannelContext->getContext());
    }

    public function aggregate(Criteria $criteria, SalesChannelContext $salesChannelContext): AggregationResultCollection
    {
        throw new \Exception('Aggregate is not implemented in static repository');
    }

    private function getDummyEntityName(): string
    {
        return $this->definition?->getEntityName() ?? 'mock';
    }
}
