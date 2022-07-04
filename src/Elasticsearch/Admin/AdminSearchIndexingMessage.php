<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

class AdminSearchIndexingMessage
{
    private string $indexer;

    private array $ids;

    public function __construct(string $indexer, array $ids)
    {
        $this->indexer = $indexer;
        $this->ids = $ids;
    }

    public function getIndexer(): string
    {
        return $this->indexer;
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
