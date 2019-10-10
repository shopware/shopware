<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;

class QueueTestIndexer implements IndexerInterface
{
    private $indexCalls = 0;

    private $partialCalls = 0;

    private $refreshCalls = 0;

    public function index(\DateTimeInterface $timestamp): void
    {
        ++$this->indexCalls;
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        ++$this->partialCalls;

        return null;
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        ++$this->refreshCalls;
    }

    public function getIndexCalls(): int
    {
        return $this->indexCalls;
    }

    public function getRefreshCalls(): int
    {
        return $this->refreshCalls;
    }

    public function reset(): void
    {
        $this->indexCalls = 0;
        $this->refreshCalls = 0;
        $this->partialCalls = 0;
    }

    public function getPartialCalls(): int
    {
        return $this->partialCalls;
    }

    public static function getName(): string
    {
        return self::class;
    }
}
