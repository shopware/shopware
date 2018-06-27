<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write;

interface EntityWriterInterface
{
    public function upsert(string $definition, array $rawData, WriteContext $writeContext): array;

    public function insert(string $resourceClass, array $rawData, WriteContext $writeContext);

    public function update(string $resourceClass, array $rawData, WriteContext $writeContext);

    public function delete(string $definition, array $ids, WriteContext $writeContext): DeleteResult;
}
