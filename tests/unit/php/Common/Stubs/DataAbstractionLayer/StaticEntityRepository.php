<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

/**
 * @internal
 */
class StaticEntityRepository extends EntityRepository
{
    /**
     * @param array<\Closure|EntitySearchResult|AggregationResultCollection|mixed|EntityCollection<Entity>|IdSearchResult> $searches
     */
    public function __construct(private array $searches)
    {
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $result = \array_shift($this->searches);

        if (\is_callable($result)) {
            $result = $result($criteria, $context);
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

        if (\is_callable($result)) {
            return $result($criteria, $context);
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
}
