<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('core')]
class IterateEntityIndexerMessage implements AsyncMessageInterface
{
    /**
     * @var string
     */
    protected $indexer;

    /**
     * @internal
     */
    public function __construct(
        string $indexer,
        protected $offset,
        protected array $skip = []
    ) {
        $this->indexer = $indexer;
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

    public function getSkip(): array
    {
        return $this->skip;
    }
}
