<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\Reversible\MigrationLock;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class MigrationLockTest extends TestCase
{
    use KernelTestBehaviour;

    private const PLUGIN = 'SwagLockTest';

    private Connection $connection;

    private MigrationLock $lock;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->lock = new MigrationLock($this->connection);
    }

    public function testReturnsTheCallbackResult(): void
    {
        static::assertSame('result', $this->lock->synchronized(self::PLUGIN, static fn (): string => 'result'));
    }

    public function testReleasesTheLockAfterASuccessfulCallback(): void
    {
        $this->lock->synchronized(self::PLUGIN, static fn (): bool => true);

        static::assertTrue($this->isFree(self::PLUGIN));
    }

    public function testReleasesTheLockWhenTheCallbackThrows(): void
    {
        try {
            $this->lock->synchronized(self::PLUGIN, static function (): void {
                throw new \RuntimeException('migration failed');
            });
            static::fail('Expected the callback exception to bubble up.');
        } catch (\RuntimeException $exception) {
            static::assertSame('migration failed', $exception->getMessage());
        }

        static::assertTrue($this->isFree(self::PLUGIN));
    }

    public function testFailsWhenAnotherConnectionHoldsTheLock(): void
    {
        $other = DriverManager::getConnection($this->connection->getParams());
        $other->fetchOne('SELECT GET_LOCK(:name, 0)', ['name' => $this->lockName(self::PLUGIN)]);

        try {
            // zero timeout so the test does not block for the production wait
            $impatient = new MigrationLock($this->connection, 0);

            $this->expectExceptionObject(MigrationException::migrationLockNotAcquired(self::PLUGIN));

            $impatient->synchronized(self::PLUGIN, static fn (): bool => true);
        } finally {
            $other->fetchOne('SELECT RELEASE_LOCK(:name)', ['name' => $this->lockName(self::PLUGIN)]);
            $other->close();
        }
    }

    private function isFree(string $plugin): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT IS_FREE_LOCK(:name)',
            ['name' => $this->lockName($plugin)]
        );
    }

    private function lockName(string $plugin): string
    {
        return MigrationLock::lockName($plugin);
    }
}
