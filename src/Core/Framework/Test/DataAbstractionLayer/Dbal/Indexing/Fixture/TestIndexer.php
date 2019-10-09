<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Indexing\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface;

class TestIndexer implements IndexerInterface
{
    /**
     * @var IndexerRegistryInterface
     */
    private $indexer;

    /**
     * @var int
     */
    private $indexCalls = 0;

    /**
     * @var int
     */
    private $refreshCalls = 0;

    public function __construct(IndexerRegistryInterface $indexer)
    {
        $this->indexer = $indexer;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        ++$this->indexCalls;
        $this->indexer->index($timestamp);
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        return null;
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        ++$this->refreshCalls;
        $this->indexer->refresh($event);
    }

    public function getIndexCalls(): int
    {
        return $this->indexCalls;
    }

    public function getRefreshCalls(): int
    {
        return $this->refreshCalls;
    }

    public static function getName(): string
    {
        return self::class;
    }
}
