<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1711461580SetSystemDefaultForDefaultMailFooter;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1711461580SetSystemDefaultForDefaultMailFooter::class)]
class Migration1711461580SetSystemDefaultForDefaultMailFooterTest extends TestCase
{
    use MigrationTestTrait;

    private Migration1711461580SetSystemDefaultForDefaultMailFooter $migration;

    private Connection $connection;

    private string $mailHeaderFooterId;

    protected function setUp(): void
    {
        $this->migration = new Migration1711461580SetSystemDefaultForDefaultMailFooter();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->mailHeaderFooterId = $this->fetchMailHeaderFooterId($this->connection);
    }

    public function testMigration(): void
    {
        $this->ensureMailHeaderFooterTemplateIsNotSystemDefault();

        $this->migration->update($this->connection);

        static::assertTrue($this->isTemplateSystemDefault());
    }

    private function fetchMailHeaderFooterId(Connection $connection): string
    {
        $id = $connection->fetchOne(
            'SELECT id FROM mail_header_footer
             ORDER BY created_at ASC
             LIMIT 1'
        );

        static::assertIsString($id);

        return $id;
    }

    private function ensureMailHeaderFooterTemplateIsNotSystemDefault(): void
    {
        if (!$this->isTemplateSystemDefault()) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE mail_header_footer
            SET system_default = 0
            WHERE id = :id',
            ['id' => $this->mailHeaderFooterId],
            ['id' => ParameterType::BINARY]
        );

        static::assertFalse($this->isTemplateSystemDefault());
    }

    private function isTemplateSystemDefault(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT system_default
             FROM mail_header_footer
             WHERE id = :id;',
            ['id' => $this->mailHeaderFooterId],
            ['id' => ParameterType::BINARY]
        );
    }
}
