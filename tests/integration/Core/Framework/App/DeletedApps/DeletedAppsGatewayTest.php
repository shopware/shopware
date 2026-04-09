<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\DeletedApps;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class DeletedAppsGatewayTest extends TestCase
{
    use IntegrationTestBehaviour;

    private DeletedAppsGateway $deletedAppsGateway;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->deletedAppsGateway = $this->getContainer()->get(DeletedAppsGateway::class);
    }

    public function testInsert(): void
    {
        $appName = 'test-app';
        $appSecret = 'secret-123';
        $this->deletedAppsGateway->insertSecretForDeletedApp($appName, $appSecret);

        $retrievedSecret = $this->connection->fetchOne(
            'SELECT app_secret FROM deleted_apps WHERE name = :name',
            ['name' => $appName]
        );

        static::assertSame($appSecret, $retrievedSecret);
    }

    public function testInsertWithDuplicateKeyUpdatesSecret(): void
    {
        $appName = 'test-app';
        $appSecret = 'secret-123';
        $this->deletedAppsGateway->insertSecretForDeletedApp($appName, $appSecret);

        $retrievedSecret = $this->connection->fetchOne(
            'SELECT app_secret FROM deleted_apps WHERE name = :name',
            ['name' => $appName]
        );

        static::assertSame($appSecret, $retrievedSecret);

        $this->deletedAppsGateway->insertSecretForDeletedApp($appName, 'new-secret');

        $retrievedSecret = $this->connection->fetchOne(
            'SELECT app_secret FROM deleted_apps WHERE name = :name',
            ['name' => $appName]
        );

        static::assertSame('new-secret', $retrievedSecret);
    }

    public function testGetSecretWhenNoEntryExistsReturnsNull(): void
    {
        static::assertNull($this->deletedAppsGateway->getDeletedAppSecret('test-app'));
    }

    public function testGetSecretReturnsTheRightOne(): void
    {
        $appName = 'test-app';
        $appSecret = 'secret-123';

        $this->connection->insert('deleted_apps', [
            'name' => $appName,
            'app_secret' => $appSecret,
        ]);
        $this->connection->insert('deleted_apps', [
            'name' => 'foo',
            'app_secret' => 'bar',
        ]);

        static::assertSame($appSecret, $this->deletedAppsGateway->getDeletedAppSecret($appName));
    }

    public function testDeleteSecretForApp(): void
    {
        $appName = 'test-app';

        $this->connection->insert('deleted_apps', [
            'name' => $appName,
            'app_secret' => 'secret-123',
        ]);
        $this->connection->insert('deleted_apps', [
            'name' => 'foo',
            'app_secret' => 'bar',
        ]);

        $this->deletedAppsGateway->deleteSecretForApp($appName);

        // make sure other entries are not affected
        static::assertSame('bar', $this->deletedAppsGateway->getDeletedAppSecret('foo'));

        static::assertNull($this->deletedAppsGateway->getDeletedAppSecret($appName));
    }

    public function testPurgeOldSecrets(): void
    {
        $appName = 'test-app';

        $this->connection->insert('deleted_apps', [
            'name' => $appName,
            'app_secret' => 'secret-123',
        ]);
        $this->connection->insert('deleted_apps', [
            'name' => 'foo',
            'app_secret' => 'bar',
        ]);

        $this->deletedAppsGateway->purgeOldSecrets();

        // make sure other entries are not affected
        static::assertNull($this->deletedAppsGateway->getDeletedAppSecret('foo'));

        static::assertNull($this->deletedAppsGateway->getDeletedAppSecret($appName));
    }
}
