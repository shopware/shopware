<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\TestCaseBase;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class DatabaseTransactionBehaviourTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

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
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        static::assertTrue($connection->isTransactionActive());
    }

    public function testSetUpIsAlsoInTransaction(): void
    {
        static::assertTrue($this->setUpIsInTransaction);
    }

    public function testLastTestCaseIsSet(): void
    {
        static::assertEquals($this->nameWithDataSet(), static::$lastTestCase);
    }

    public function testTransactionOpenWithoutClose(): void
    {
        static::expectException(ExpectationFailedException::class);
        static::expectExceptionMessage('The previous test case\'s transaction was not closed properly');
        static::expectExceptionMessage('Previous Test case: ' . (new \ReflectionClass($this))->getName() . '::' . static::$lastTestCase);
        static::startTransactionBefore();
    }
}
