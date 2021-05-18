<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Test\Migration\MDEV25672\MigrationMariadbMDEV25672BugPart1;
use Shopware\Core\Framework\Test\Migration\MDEV25672\MigrationMariadbMDEV25672BugPart2;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class MDEV25672WorkaroundTest extends TestCase
{
    use KernelTestBehaviour;

    private MigrationSource $source;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->connection->executeStatement('DROP TABLE IF EXISTS t1');

        $this->source = new MigrationSource('test');
        $this->source->addDirectory(__DIR__ . '/MDEV25672', 'Shopware\Core\Framework\Test\Migration\MDEV25672');
    }

    public function tearDown(): void
    {
        $this->connection->delete('migration', ['`class`' => MigrationMariadbMDEV25672BugPart1::class]);
        $this->connection->delete('migration', ['`class`' => MigrationMariadbMDEV25672BugPart2::class]);

        $this->connection->executeStatement('DROP TABLE IF EXISTS t1');
    }

    public function testUpdateMDEV25672Workaround(): void
    {
        $this->prepareMigrations(false);

        $runtime = new MigrationRuntime(
            $this->connection,
            $this->createMock(LoggerInterface::class)
        );
        $result = iterator_to_array($runtime->migrate($this->source));

        static::assertCount(2, $result);
    }

    public function testUpdateDestructiveMDEV25672Workaround(): void
    {
        $this->prepareMigrations(true);

        $runtime = new MigrationRuntime(
            $this->connection,
            $this->createMock(LoggerInterface::class)
        );
        $result = iterator_to_array($runtime->migrateDestructive($this->source));

        static::assertCount(2, $result);
    }

    private function prepareMigrations(bool $destructive): void
    {
        $update = $destructive ? (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null;
        $this->connection->insert('`migration`', [
            '`class`' => MigrationMariadbMDEV25672BugPart1::class,
            '`creation_timestamp`' => 1536232601,
            '`update`' => $update,
            '`update_destructive`' => null,
        ]);

        $this->connection->insert('`migration`', [
            '`class`' => MigrationMariadbMDEV25672BugPart2::class,
            '`creation_timestamp`' => 1536232602,
            '`update`' => $update,
            '`update_destructive`' => null,
        ]);
    }
}
