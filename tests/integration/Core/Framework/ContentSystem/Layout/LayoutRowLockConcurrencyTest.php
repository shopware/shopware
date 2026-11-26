<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutRowLock;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * Lock contention needs a second connection that sees the layout row, so the layout is committed rather than
 * wrapped in the test transaction {@see LayoutRowLockTest} uses.
 *
 * @internal
 */
#[Package('framework')]
class LayoutRowLockConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private string $layoutId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->layoutId = Uuid::randomHex();

        $this->repository()->create([[
            'id' => $this->layoutId,
            'name' => 'row-lock-' . $this->layoutId,
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [['id' => 'element', 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []]],
        ]], Context::createDefaultContext());
    }

    protected function tearDown(): void
    {
        $this->repository()->delete([['id' => $this->layoutId]], Context::createDefaultContext());
    }

    #[TestDox('another writer cannot lock the layout row while an operation runs under the lock, and can once it finished')]
    public function testAnotherWriterWaitsForTheLock(): void
    {
        $other = DriverManager::getConnection($this->connection->getParams());
        $other->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $blocked = $this->rowLock()->run($this->layoutId, fn (): bool => $this->tryLock($other));

            static::assertTrue($blocked);
            static::assertFalse($this->tryLock($other));
        } finally {
            $other->close();
        }
    }

    /**
     * @return bool whether the lock wait timed out
     */
    private function tryLock(Connection $other): bool
    {
        $other->beginTransaction();

        try {
            $other->fetchOne(
                'SELECT 1 FROM `content_layout` WHERE `id` = :id FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($this->layoutId)],
            );

            return false;
        } catch (LockWaitTimeoutException) {
            return true;
        } finally {
            $other->rollBack();
        }
    }

    private function rowLock(): LayoutRowLock
    {
        return static::getContainer()->get(LayoutRowLock::class);
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        return static::getContainer()->get('content_layout.repository');
    }
}
