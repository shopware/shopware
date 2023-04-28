<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

/**
 * @internal
 *
 * @phpstan-type ResultTypes = EntitySearchResult|AggregationResultCollection|mixed|EntityCollection<Entity>|IdSearchResult
 */
class StaticEntityRepository extends EntityRepository
{
    /**
     * @var array<array<mixed>>
     */
    public array $upserts = [];

    /**
     * @var array<array<mixed>>
     */
    public array $updates = [];

    /**
     * @var array<array<mixed>>
     */
    public array $creates = [];

    /**
     * @var array<array<string, mixed|null>>
     */
    public array $deletes = [];

    /**
     * @param array<callable(Criteria, Context): (ResultTypes)|ResultTypes> $searches
     */
    public function __construct(
        private array $searches,
        private readonly ?EntityDefinition $definition = null
    ) {
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $result = \array_shift($this->searches);
        $callable = $result;

        if (\is_callable($callable)) {
            /** @var callable(Criteria, Context): ResultTypes $callable */
            $result = $callable($criteria, $context);
        }

        if ($result instanceof EntitySearchResult) {
            return $result;
        }

        if ($result instanceof EntityCollection) {
            return new EntitySearchResult('mock', $result->count(), $result, null, $criteria, $context);
        }

        if ($result instanceof AggregationResultCollection) {
            return new EntitySearchResult('mock', 0, new EntityCollection(), $result, $criteria, $context);
        }

        throw new \RuntimeException('Invalid mock repository configuration');
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $result = \array_shift($this->searches);
        $callable = $result;

        if (\is_callable($callable)) {
            /** @var callable(Criteria, Context): ResultTypes $callable */
            $result = $callable($criteria, $context);
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

        return new IdSearchResult(\count($result), $result, $criteria, $context);
    }

    /**
     * @experimental
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->creates[] = $data;

        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
    }

    /**
     * @experimental
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->updates[] = $data;

        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
    }

    /**
     * @experimental
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->upserts[] = $data;

        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
    }

    /**
     * @experimental
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $this->deletes[] = $ids;

        return EntityWrittenContainerEvent::createWithDeletedEvents([], Context::createDefaultContext(), []);
    }

    public function getDefinition(): EntityDefinition
    {
        if ($this->definition === null) {
            throw new \RuntimeException('No definition set');
        }

        return $this->definition;
    }
}
