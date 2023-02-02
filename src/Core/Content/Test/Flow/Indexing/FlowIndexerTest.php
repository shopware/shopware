<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Indexing\FlowIndexer;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;

/**
 * @internal
 */
class FlowIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    public function testIndexingHappensAfterPluginLifecycle(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeStatement('UPDATE `flow` SET `payload` = null, `invalid` = 0');

        $indexer = $this->getContainer()->get(FlowIndexer::class);
        $indexer->refreshPlugin();

        $this->runWorker();

        static::assertGreaterThan(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM flow WHERE payload IS NOT NULL'));
    }
}
