<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('system-settings')]
final class AdminSearchIndexingMessage
{
    private string $entity;

    private string $indexer;

    /**
     * @var array<string, string>
     */
    private array $indices;

    /**
     * @var array<string>
     */
    private array $ids;

    /**
     * @param array<string, string> $indices
     * @param array<string> $ids
     */
    public function __construct(string $entity, string $indexer, array $indices, array $ids)
    {
        $this->entity = $entity;
        $this->indexer = $indexer;
        $this->indices = $indices;
        $this->ids = $ids;
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
