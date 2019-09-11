<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

class IndexerMessage
{
    public const ACTION_INDEX = 'index';
    public const ACTION_REFRESH = 'refresh';
    public const ACTION_PARTIAL = 'partial';

    /**
     * @var string classname of the indexer
     */
    private $indexer;

    /**
     * @var \DateTimeInterface
     */
    private $timestamp;

    /**
     * @var string
     */
    private $actionType = self::ACTION_INDEX;

    /**
     * @var array|null
     */
    private $lastId;

    /**
     * @var EntityWrittenContainerEvent
     */
    private $entityWrittenContainerEvent;

    public function getIndexer(): string
    {
        return $this->indexer;
    }

    public function getTimestamp(): \DateTimeInterface
    {
        return $this->timestamp;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function getEntityWrittenContainerEvent(): EntityWrittenContainerEvent
    {
        return $this->entityWrittenContainerEvent;
    }

    public function setIndexer(string $indexer): void
    {
        $this->indexer = $indexer;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function setActionType(string $actionType): void
    {
        $this->actionType = $actionType;
    }

    public function setEntityWrittenContainerEvent(EntityWrittenContainerEvent $entityWrittenContainerEvent): void
    {
        $this->entityWrittenContainerEvent = $entityWrittenContainerEvent;
    }

    public function getLastId(): ?array
    {
        return $this->lastId;
    }

    public function setLastId(?array $lastId): void
    {
        $this->lastId = $lastId;
    }
}
