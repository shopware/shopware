<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessageSender;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;

class IndexerMessageSenderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var QueueTestIndexer[]
     */
    private $indexer;

    /**
     * @var IndexerMessageSender
     */
    private $indexerMessageSender;

    public function setUp(): void
    {
        $bus = $this->getBus();
        $this->indexer = [
            $this->getContainer()->get("Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Indexing\QueueTestIndexer"),
        ];

        $this->indexerMessageSender = new IndexerMessageSender($bus, $this->indexer);
    }

    public function testIndexing(): void
    {
        $this->indexer[0]->reset();
        $this->indexerMessageSender->index(new \DateTime());

        $this->runWorker();

        static::assertEquals(1, $this->indexer[0]->getIndexCalls());
    }

    public function testRefresh(): void
    {
        $this->indexer[0]->reset();
        $this->indexerMessageSender->refresh($this->createMock(EntityWrittenContainerEvent::class));

        $this->runWorker();

        static::assertEquals(1, $this->indexer[0]->getRefreshCalls());
    }
}

class QueueTestIndexer implements IndexerInterface
{
    private $indexCalls = 0;
    private $refreshCalls = 0;

    public function index(\DateTimeInterface $timestamp): void
    {
        ++$this->indexCalls;
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
    }
}
