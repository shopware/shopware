<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Indexing;

use PHPUnit\Framework\TestCase;
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
            $this->getContainer()->get(QueueTestIndexer::class),
        ];

        $this->indexerMessageSender = new IndexerMessageSender($bus, $this->indexer);
    }

    public function testIndexing(): void
    {
        $this->indexer[0]->reset();
        $this->indexerMessageSender->partial(new \DateTime());

        $this->runWorker();

        static::assertEquals(1, $this->indexer[0]->getPartialCalls());
    }
}
