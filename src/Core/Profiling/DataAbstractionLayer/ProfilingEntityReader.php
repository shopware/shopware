<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ProfilingEntityReader implements EntityReaderInterface
{
    public function __construct(
        private readonly EntityReaderInterface $decorated,
        private readonly DalCollector $collector,
    ) {
    }

    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection
    {
        return $this->collector->traceRead(
            DalCollector::OPERATION_READ,
            $definition,
            $criteria,
            $context,
            fn (): EntityCollection => $this->decorated->read($definition, $criteria, $context),
        );
    }
}
