<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

class IndexerMessage
{
    /**
     * @var string[]
     */
    private $indexerNames;

    /**
     * @var \DateTimeInterface
     */
    private $timestamp;

    /**
     * @var array|null
     */
    private $offset;

    public function __construct(array $indexers)
    {
        if (empty($indexers)) {
            throw new \InvalidArgumentException('$indexers may not be empty');
        }
        foreach ($indexers as $indexer) {
            if (!is_string($indexer)) {
                throw new \InvalidArgumentException('expected array of strings');
            }
        }
        $this->indexerNames = $indexers;
    }

    public function getCurrentIndexerName(): string
    {
        return @current($this->indexerNames);
    }

    public function getIndexerNames(): array
    {
        return $this->indexerNames;
    }

    public function getTimestamp(): \DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getOffset(): ?array
    {
        return $this->offset;
    }

    public function setOffset(?array $offset): void
    {
        $this->offset = $offset;
    }
}
