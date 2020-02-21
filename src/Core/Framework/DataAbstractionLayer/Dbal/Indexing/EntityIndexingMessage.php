<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing;

class EntityIndexingMessage
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var mixed
     */
    protected $offset;

    /**
     * @var string
     */
    protected $indexer;

    public function __construct(array $data, $offset = null)
    {
        $this->data = $data;
        $this->offset = $offset;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @internal This property is called by the indexer registry. The indexer name is stored in this message to identify the message handler in the queue worker
     */
    public function getIndexer(): string
    {
        return $this->indexer;
    }

    /**
     * @internal This property is called by the indexer registry. The indexer name is stored in this message to identify the message handler in the queue worker
     */
    public function setIndexer(string $indexer): void
    {
        $this->indexer = $indexer;
    }
}
