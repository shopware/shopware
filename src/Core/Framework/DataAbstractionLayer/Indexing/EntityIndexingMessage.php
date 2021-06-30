<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Context;

class EntityIndexingMessage
{
    protected $data;

    protected $offset;

    /**
     * @var string
     */
    protected $indexer;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var bool
     */
    private $forceQueue;

    private array $skip = [];

    public function __construct($data, $offset = null, ?Context $context = null, bool $forceQueue = false)
    {
        $this->data = $data;
        $this->offset = $offset;
        $this->context = $context ?? Context::createDefaultContext();
        $this->forceQueue = $forceQueue;
    }

    public function getData()
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

    public function getContext(): Context
    {
        return $this->context;
    }

    public function forceQueue(): bool
    {
        return $this->forceQueue;
    }

    public function getSkip(): array
    {
        return $this->skip;
    }

    public function setSkip(array $skip): void
    {
        $this->skip = $skip;
    }

    public function allow(string $name): bool
    {
        return !\in_array($name, $this->getSkip(), true);
    }
}
