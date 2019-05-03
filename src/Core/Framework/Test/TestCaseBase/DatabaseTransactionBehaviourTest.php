<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class DatabaseTransactionBehaviourTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var bool
     */
    private $setUpIsInTransaction = false;

    protected function setUp(): void
    {
        $this->setUpIsInTransaction = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class)
            ->isTransactionActive();
    }

    protected function tearDown(): void
    {
        $tearDownIsInTransaction = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class)
            ->isTransactionActive();

        if (!$tearDownIsInTransaction) {
            throw new \RuntimeException('TearDown does not work correctly');
        }
    }

    public function testInTransaction(): void
    {
        /** @var Connection $connection */
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        static::assertTrue($connection->isTransactionActive());
    }

    public function testSetUpIsAlsoInTransaction(): void
    {
        static::assertTrue($this->setUpIsInTransaction);
    }
}
