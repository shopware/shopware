<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1605103533AddCookiesToAppEntity;

class Migration1605103533AddCookiesToAppEntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function tearDown(): void
    {
        $this->connection->executeUpdate('DELETE FROM `app`');
        $this->connection->executeUpdate('DELETE FROM `integration`');
        $this->connection->executeUpdate('DELETE FROM `acl_role`');
    }

    public function testMigrationWorksOnNonEmptyTable(): void
    {
        $this->connection->executeUpdate('ALTER TABLE `app` DROP COLUMN `cookies`');

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

        static::assertNull($this->connection->fetchColumn('SELECT `cookies` FROM `app`'));
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

        static::assertNull($this->connection->fetchColumn('SELECT `cookies` FROM `app`'));
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
