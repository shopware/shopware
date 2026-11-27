<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ProfilingEntityWriter implements EntityWriterInterface
{
    public function __construct(
        private readonly EntityWriterInterface $decorated,
        private readonly DalCollector $collector,
    ) {
    }

    public function sync(array $operations, WriteContext $context): WriteResult
    {
        return $this->collector->traceSync(
            $operations,
            $context,
            fn (): WriteResult => $this->decorated->sync($operations, $context),
        );
    }

    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->collector->traceWrite(
            DalCollector::OPERATION_UPSERT,
            $definition,
            $rawData,
            $writeContext,
            fn (): array => $this->decorated->upsert($definition, $rawData, $writeContext),
        );
    }

    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->collector->traceWrite(
            DalCollector::OPERATION_INSERT,
            $definition,
            $rawData,
            $writeContext,
            fn (): array => $this->decorated->insert($definition, $rawData, $writeContext),
        );
    }

    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->collector->traceWrite(
            DalCollector::OPERATION_UPDATE,
            $definition,
            $rawData,
            $writeContext,
            fn (): array => $this->decorated->update($definition, $rawData, $writeContext),
        );
    }

    public function delete(EntityDefinition $definition, array $rawData, WriteContext $writeContext): WriteResult
    {
        return $this->collector->traceWrite(
            DalCollector::OPERATION_DELETE,
            $definition,
            $rawData,
            $writeContext,
            fn (): WriteResult => $this->decorated->delete($definition, $rawData, $writeContext),
        );
    }
}
