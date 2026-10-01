<?php

declare(strict_types=1);

namespace Shopware\Core\Test\Stub\DataAbstractionLayer;

use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class StaticEntityWriter implements EntityWriterInterface
{
    /**
     * @var list<SyncOperation>
     */
    public array $syncs = [];

    /**
     * @var array<array<string, mixed>>
     */
    public array $upserts = [];

    /**
     * @var array<array<string, mixed>>
     */
    public array $inserts = [];

    /**
     * @var array<array<string, mixed>>
     */
    public array $updates = [];

    /**
     * @var array<array<string, string>>
     */
    public array $deletes = [];

    public function sync(array $operations, WriteContext $context): WriteResult
    {
        $this->syncs = [...$this->syncs, ...$operations];

        return new WriteResult([]);
    }

    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->upserts = [...$this->upserts, ...$rawData];

        return [];
    }

    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->inserts = [...$this->inserts, ...$rawData];

        return [];
    }

    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->updates = [...$this->updates, ...$rawData];

        return [];
    }

    public function delete(EntityDefinition $definition, array $rawData, WriteContext $writeContext): WriteResult
    {
        $this->deletes = [...$this->deletes, ...$rawData];

        return new WriteResult([]);
    }
}
