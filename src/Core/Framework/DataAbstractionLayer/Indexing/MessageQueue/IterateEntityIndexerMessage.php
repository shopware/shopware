<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('framework')]
class IterateEntityIndexerMessage implements AsyncMessageInterface
{
    /**
     * @internal
     *
     * @param array{offset: int|null}|null $offset
     * @param array<string> $skip
     */
    public function __construct(
        protected string $indexer,
        protected ?array $offset,
        protected array $skip = []
    ) {
    }

    public function getIndexer(): string
    {
        return $this->indexer;
    }

    /**
     * @return array{offset: int|null}|null
     */
    public function getOffset(): ?array
    {
        return $this->offset;
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    public function setOffset(?array $offset): void
    {
        $this->offset = $offset;
    }

    /**
     * @return array<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }
}
