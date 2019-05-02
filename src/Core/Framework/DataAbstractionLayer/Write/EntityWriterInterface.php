<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

interface EntityWriterInterface
{
    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array;

    public function insert(EntityDefinition $resourceClass, array $rawData, WriteContext $writeContext);

    public function update(EntityDefinition $resourceClass, array $rawData, WriteContext $writeContext);

    public function delete(EntityDefinition $definition, array $ids, WriteContext $writeContext): DeleteResult;
}
