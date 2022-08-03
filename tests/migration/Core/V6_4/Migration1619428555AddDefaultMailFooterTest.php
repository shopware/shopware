<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1619428555AddDefaultMailFooter;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1619428555AddDefaultMailFooter
 */
class Migration1619428555AddDefaultMailFooterTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DELETE FROM mail_header_footer');
    }

    public function testMigration(): void
    {
        $migration = new Migration1619428555AddDefaultMailFooter();

        // Assert that the table is empty
        static::assertEquals(0, $this->getMailFooter());
        static::assertEquals(0, $this->getMailFooterTranslation());

        $migration->update($this->connection);

        // Assert that records have been inserted
        static::assertEquals(1, $this->getMailFooter());
        static::assertEquals(2, $this->getMailFooterTranslation());
    }

    private function getMailFooter(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(id) as amount FROM `mail_header_footer`');
    }

    private function getMailFooterTranslation(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(mail_header_footer_id) as amount FROM `mail_header_footer_translation`');
    }
}
