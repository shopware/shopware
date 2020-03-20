<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

class IterateEntityIndexerMessage
{
    /**
     * @var string
     */
    protected $indexer;

    /**
     * @var null|mixed
     */
    protected $offset;

    public function __construct(string $indexer, $offset)
    {
        $this->indexer = $indexer;
        $this->offset = $offset;
    }

    public function getIndexer(): string
    {
        return $this->indexer;
    }

    public function getOffset()
    {
        return $this->offset;
    }

    public function setOffset($offset): void
    {
        $this->offset = $offset;
    }
}
