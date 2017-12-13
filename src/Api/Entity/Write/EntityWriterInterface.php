<?php declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: oliver
 * Date: 27.10.17
 * Time: 10:24
 */

namespace Shopware\Api\Entity\Write;

interface EntityWriterInterface
{
    public function upsert(string $definition, array $rawData, WriteContext $writeContext): array;

    public function insert(string $resourceClass, array $rawData, WriteContext $writeContext);

    public function update(string $resourceClass, array $rawData, WriteContext $writeContext);
}
