<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\DeletedApps;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @CodeCoverageIgnore only integration tested
 */
#[Package('framework')]
class DeletedAppsGateway
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function insertSecretForDeletedApp(string $appName, string $appSecret): void
    {
        $this->connection->insert('deleted_apps', [
            'name' => $appName,
            'app_secret' => $appSecret,
        ]);
    }

    public function getDeletedAppSecret(string $appName): ?string
    {
        $oldSecret = $this->connection->fetchOne('SELECT app_secret FROM deleted_apps WHERE name = :name', ['name' => $appName]);

        return $oldSecret === false ? null : (string) $oldSecret;
    }

    public function deleteSecretForApp(string $appName): void
    {
        $this->connection->executeStatement('DELETE FROM deleted_apps WHERE name = :name', ['name' => $appName]);
    }

    public function purgeOldSecrets(): void
    {
        $this->connection->executeStatement('DELETE FROM deleted_apps');
    }
}
