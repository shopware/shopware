<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('core')]
class EntityIndexingMessage implements AsyncMessageInterface
{
    /**
     * @var string
     */
    protected $indexer;

    private readonly Context $context;

    /**
     * @var array<string>
     */
    private array $skip = [];

    public function __construct(
        protected $data,
        protected $offset = null,
        ?Context $context = null,
        private readonly bool $forceQueue = false
    ) {
        $this->context = $context ?? Context::createDefaultContext();
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

    /**
     * @return array<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }

    /**
     * @param array<string> $skip
     */
    public function setSkip(array $skip): void
    {
        $this->skip = \array_unique(\array_values($skip));
    }

    public function addSkip(string ...$skip): void
    {
        $this->skip = \array_unique(\array_merge($this->skip, \array_values($skip)));
    }

    public function allow(string $name): bool
    {
        return !\in_array($name, $this->getSkip(), true);
    }
}
