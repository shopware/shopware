<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1784049595MigrateAppCookiesToAppFeature extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784049595;
    }

    public function update(Connection $connection): void
    {
        $apps = $connection->fetchAllAssociative(
            'SELECT `id`, `name`, `cookies` FROM `app` WHERE `cookies` IS NOT NULL AND `cookies` != \'[]\''
        );

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($apps as $app) {
            $groups = json_decode((string) $app['cookies'], true);
            if (!\is_array($groups)) {
                continue;
            }

            foreach ($groups as $group) {
                if (!\is_array($group) || !isset($group['snippet_name'])) {
                    continue;
                }

                if (isset($group['expiration'])) {
                    $group['expiration'] = (int) $group['expiration'];
                }

                $connection->insert('app_feature', [
                    'id' => Uuid::randomBytes(),
                    'app_id' => $app['id'],
                    'app_name' => $app['name'],
                    'type' => 'cookie',
                    'name' => (string) $group['snippet_name'],
                    'payload' => json_encode($group, \JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // app cookies are stored in the generic app_feature table now

        // the JSON_VALID check constraint references the column, so it must go first
        try {
            $connection->executeStatement('ALTER TABLE `app` DROP CHECK `json.app.cookies`');
        } catch (\Throwable) {
            // constraint may already be gone on some installations
        }

        $this->dropColumnIfExists($connection, 'app', 'cookies');
    }
}
