<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1605103533AddCookiesToAppEntity;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1605103533AddCookiesToAppEntity
 */
class Migration1605103533AddCookiesToAppEntityTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('DELETE FROM `app`');
        $this->connection->executeStatement('DELETE FROM `integration`');
        $this->connection->executeStatement('DELETE FROM `acl_role`');
        $this->connection->beginTransaction();
    }

    public function testMigrationWorksOnNonEmptyTable(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('ALTER TABLE `app` DROP COLUMN `cookies`');

        $this->connection->insert('app', [
            'id' => Uuid::randomBytes(),
            'name' => 'test',
            'path' => __DIR__,
            'version' => '1.0.0',
            'integration_id' => $this->getIntegrationId(),
            'acl_role_id' => $this->getRoleId(),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1605103533AddCookiesToAppEntity();
        $migration->update($this->connection);

        $this->connection->beginTransaction();

        static::assertNull($this->connection->fetchOne('SELECT `cookies` FROM `app`'));
    }

    public function testInsertWorksWithoutCookiesAfterMigration(): void
    {
        $this->connection->insert('app', [
            'id' => Uuid::randomBytes(),
            'name' => 'test',
            'path' => __DIR__,
            'version' => '1.0.0',
            'integration_id' => $this->getIntegrationId(),
            'acl_role_id' => $this->getRoleId(),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        static::assertNull($this->connection->fetchOne('SELECT `cookies` FROM `app`'));
    }

    private function getRoleId(): string
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test',
            'privileges' => '[]',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }

    private function getIntegrationId(): string
    {
        $integrationId = Uuid::randomBytes();

        $this->connection->insert('integration', [
            'id' => $integrationId,
            'access_key' => 'test',
            'secret_access_key' => 'test',
            'label' => 'test',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $integrationId;
    }
}
