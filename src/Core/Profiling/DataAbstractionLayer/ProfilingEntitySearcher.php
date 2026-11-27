<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ProfilingEntitySearcher implements EntitySearcherInterface
{
    public function __construct(
        private readonly EntitySearcherInterface $decorated,
        private readonly DalCollector $collector,
    ) {
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->collector->traceRead(
            DalCollector::OPERATION_SEARCH_IDS,
            $definition,
            $criteria,
            $context,
            fn (): IdSearchResult => $this->decorated->search($definition, $criteria, $context),
        );
    }
}
