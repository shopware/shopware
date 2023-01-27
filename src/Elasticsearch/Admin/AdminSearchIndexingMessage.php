<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('system-settings')]
final class AdminSearchIndexingMessage
{
    /**
     * @param array<string, string> $indices
     * @param array<string> $ids
     */
    public function __construct(
        private readonly string $entity,
        private readonly string $indexer,
        private readonly array $indices,
        private readonly array $ids
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
}
