<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Entity;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

/**
 * @deprecated tag:v6.5.0 - Will be removed, use the static Profiler::trace method to directly trace functions
 */
class EntitySearcherProfiler implements EntitySearcherInterface
{
    private EntitySearcherInterface $decorated;

    /**
     * @internal
     */
    public function __construct(EntitySearcherInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->decorated->search($definition, $criteria, $context);
    }
}
