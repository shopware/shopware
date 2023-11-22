<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DbalDriverException;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1670090989AddIndexOrderOrderNumber;

/**
 * @internal
 */
#[CoversClass(Migration1670090989AddIndexOrderOrderNumber::class)]
class Migration1670090989AddIndexOrderOrderNumberTest extends TestCase
{
    private Connection $connection;

    /**
     * @throws DbalDriverException
     * @throws DbalException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->removeIndexIfExists();
    }

    /**
     * @throws DbalDriverException
     * @throws DbalException
     */
    public function testOrderOrderNumberIndexWillBeAdded(): void
    {
        $this->executeMigration();

        static::assertTrue($this->isIndexCreated());
    }

    public function testRepetitiveMigrationExecution(): void
    {
        $e = null;

        try {
            $this->executeMigration();
            $this->executeMigration();
        } catch (DbalException|DbalDriverException $e) {
            static::fail($e->getMessage());
        }

        static::assertNull($e);
    }

    /**
     * @throws DbalDriverException
     * @throws DbalException
     */
    private function executeMigration(): void
    {
        (new Migration1670090989AddIndexOrderOrderNumber())->update($this->connection);
    }

    /**
     * @throws DbalException
     * @throws DbalDriverException
     */
    private function removeIndexIfExists(): void
    {
        if (!$this->isIndexCreated()) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `order` DROP KEY `idx.order_number`');
    }

    /**
     * @throws DbalException
     * @throws DbalDriverException
     */
    private function isIndexCreated(): bool
    {
        $key = $this->connection->executeQuery(
            'SHOW KEYS FROM `order` WHERE Column_name="order_number" AND Key_name="idx.order_number"'
        )->fetchAssociative();

        return !empty($key);
    }
}
