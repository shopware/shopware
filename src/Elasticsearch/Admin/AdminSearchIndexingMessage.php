<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 */
#[Package('inventory')]
final class AdminSearchIndexingMessage implements AsyncMessageInterface
{
    /**
     * @param array<string, string> $indices
     * @param array<string> $ids
     * @param array<string> $toRemoveIds
     */
    public function __construct(
        private string $entity,
        private string $indexer,
        private array $indices,
        private array $ids,
        private array $toRemoveIds = []
    ) {
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getIndexer(): string
    {
        return $this->indexer;
    }

    /**
     * @return array<string, string>
     */
    public function getIndices(): array
    {
        return $this->indices;
    }

    /**
     * @return array<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @return array<string>
     */
    public function getToRemoveIds(): array
    {
        return $this->toRemoveIds;
    }
}
