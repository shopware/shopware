<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\DeletedApps;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\App\DeletedApps\DeletedAppsGatewayTest
 */
#[Package('framework')]
readonly class DeletedAppsGateway
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param list<string> $unconfirmedAppSecrets
     */
    public function insertSecretsForDeletedApp(
        string $appName,
        #[\SensitiveParameter]
        string $appSecret,
        #[\SensitiveParameter]
        array $unconfirmedAppSecrets = []
    ): void {
        $this->connection->executeStatement('
            INSERT INTO deleted_apps (name, app_secret, unconfirmed_app_secrets)
            VALUES (:name, :app_secret, :unconfirmed_app_secrets)
            ON DUPLICATE KEY UPDATE app_secret = VALUES(app_secret), unconfirmed_app_secrets = VALUES(unconfirmed_app_secrets)
        ', [
            'name' => $appName,
            'app_secret' => $appSecret,
            'unconfirmed_app_secrets' => $unconfirmedAppSecrets === []
                ? null
                : json_encode($unconfirmedAppSecrets, \JSON_THROW_ON_ERROR),
        ]);
    }

    public function getDeletedAppSecret(string $appName): ?string
    {
        $oldSecret = $this->connection->fetchOne('SELECT app_secret FROM deleted_apps WHERE name = :name', ['name' => $appName]);

        return $oldSecret === false ? null : (string) $oldSecret;
    }

    /**
     * Secrets the app may hold but this shop never committed, most-recent first.
     *
     * @return list<string>|null
     */
    public function getDeletedAppUnconfirmedSecrets(string $appName): ?array
    {
        $secrets = $this->connection->fetchOne(
            'SELECT unconfirmed_app_secrets FROM deleted_apps WHERE name = :name',
            ['name' => $appName]
        );

        if (!\is_string($secrets)) {
            return null;
        }

        /** @var list<string> $unconfirmedAppSecrets */
        $unconfirmedAppSecrets = json_decode($secrets, true, 512, \JSON_THROW_ON_ERROR);

        return $unconfirmedAppSecrets === [] ? null : $unconfirmedAppSecrets;
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
