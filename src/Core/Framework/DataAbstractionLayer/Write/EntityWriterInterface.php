<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal use entity repository to write data
 */
#[Package('core')]
interface EntityWriterInterface
{
    public function sync(array $operations, WriteContext $context): WriteResult;

    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array;

    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext);

    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext);

    public function delete(EntityDefinition $definition, array $ids, WriteContext $writeContext): WriteResult;
}
