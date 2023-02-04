<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1647260673AddIndexForEmail;
use function array_column;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1647260673AddIndexForEmail
 */
class Migration1647260673AddIndexForEmailTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        // Kill index if exists
        try {
            $this->connection->executeStatement('DROP INDEX `idx.email` ON customer');
        } catch (\Throwable) {
        }
    }

    public function testOnceCreation(): void
    {
        $m = new Migration1647260673AddIndexForEmail();
        $m->update($this->connection);

        $this->assertIndexExists();
    }

    public function testMultiCreation(): void
    {
        $m = new Migration1647260673AddIndexForEmail();
        $m->update($this->connection);
        $m->update($this->connection);

        $this->assertIndexExists();
    }

    private function assertIndexExists(): void
    {
        $keys = array_column($this->connection->fetchAllAssociative('SHOW INDEX FROM customer'), 'Key_name');

        static::assertContains('idx.email', $keys);
    }
}
