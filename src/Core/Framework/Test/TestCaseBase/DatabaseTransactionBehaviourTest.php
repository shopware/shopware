<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;


class DatabaseTransactionBehaviourTest extends TestCase
{
    use DatabaseTransactionBehaviour;

    /**
     * @var bool
     */
    private $setUpIsInTransaction = false;

    /**
     * @var bool
     */
    private $tearDownIsInTransaction = false;

    protected function setUp()
    {
        $this->setUpIsInTransaction = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class)
            ->isTransactionActive();
    }

    protected function tearDown()
    {
        $tearDownIsInTransaction = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class)
            ->isTransactionActive();

        if(!$tearDownIsInTransaction) {
            throw new \RuntimeException('TearDown does not work correctly');
        }
    }

    public function testInTransaction(): void
    {
        /** @var Connection $connection */
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        self::assertTrue($connection->isTransactionActive());
    }

    public function testSetUpIsAlsoInTransaction()
    {
        self::assertTrue($this->setUpIsInTransaction);
    }
}
