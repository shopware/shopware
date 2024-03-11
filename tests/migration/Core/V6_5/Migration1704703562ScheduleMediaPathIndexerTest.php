<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1704703562ScheduleMediaPathIndexer;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1704703562ScheduleMediaPathIndexer::class)]
class Migration1704703562ScheduleMediaPathIndexerTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrate(): void
    {
        $queuer = $this->getContainer()->get(IndexerQueuer::class);
        $queuer->finishIndexer(['media.path.post_update']);
        $queuedIndexers = $queuer->getIndexers();

        static::assertArrayNotHasKey('media.path.post_update', $queuedIndexers);

        $m = new Migration1704703562ScheduleMediaPathIndexer();
        $m->update($this->connection);
        $m->update($this->connection);

        $queuedIndexers = $queuer->getIndexers();
        static::assertArrayHasKey('media.path.post_update', $queuedIndexers);
    }
}
