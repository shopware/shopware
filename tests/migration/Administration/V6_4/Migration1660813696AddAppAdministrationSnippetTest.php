<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Administration\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Migration\V6_4\Migration1660813696AddAppAdministrationSnippet;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 *
 * @covers \Shopware\Administration\Migration\V6_4\Migration1660813696AddAppAdministrationSnippet
 */
class Migration1660813696AddAppAdministrationSnippetTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $expectedColumns = [
        'id',
        'app_id',
        'locale_id',
        'value',
        'created_at',
        'updated_at',
    ];

    public function testGetCreationTimestamp(): void
    {
        $expectedTimestamp = 1660813696;
        $migration = new Migration1660813696AddAppAdministrationSnippet();

        static::assertEquals($expectedTimestamp, $migration->getCreationTimestamp());
    }

    public function testMultipleExecutions(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1660813696AddAppAdministrationSnippet();

        $migration->update($connection);
        $migration->update($connection);

        $this->assertColumnsExists($connection);
    }

    public function testTableGetsCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1660813696AddAppAdministrationSnippet();

        $connection->executeStatement('DROP TABLE IF EXISTS `app_administration_snippet`;');
        $migration->update($connection);

        $this->assertColumnsExists($connection);
    }

    private function assertColumnsExists(Connection $connection): void
    {
        foreach ($this->expectedColumns as $expectedColumn) {
            static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'app_administration_snippet', $expectedColumn));
        }
    }
}
