<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1710493619ScheduleMediaPathIndexer;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1710493619ScheduleMediaPathIndexer::class)]
class Migration1710493619ScheduleMediaPathIndexerTest extends TestCase
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

        $m = new Migration1710493619ScheduleMediaPathIndexer();
        $m->update($this->connection);
        $m->update($this->connection);

        $queuedIndexers = $queuer->getIndexers();
        static::assertArrayHasKey('media.path.post_update', $queuedIndexers);
    }
}
