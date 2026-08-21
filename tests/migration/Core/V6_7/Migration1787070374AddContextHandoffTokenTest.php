<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1787070374AddContextHandoffToken;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1787070374AddContextHandoffToken::class)]
class Migration1787070374AddContextHandoffTokenTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1787070374, (new Migration1787070374AddContextHandoffToken())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $this->rollback();

        $migration = new Migration1787070374AddContextHandoffToken();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'context_handoff_token', 'token'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'context_handoff_token', 'context_token'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'context_handoff_token', 'expires'));
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `context_handoff_token`');
    }
}
