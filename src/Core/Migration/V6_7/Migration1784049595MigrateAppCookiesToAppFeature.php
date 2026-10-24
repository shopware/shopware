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

        $connection->transactional(function (Connection $connection) use ($apps, $now): void {
            foreach ($apps as $app) {
                $groups = json_decode((string) $app['cookies'], true);
                if (!\is_array($groups)) {
                    continue;
                }

                $merged = [];
                foreach ($groups as $group) {
                    if (!\is_array($group) || !isset($group['snippet_name'])) {
                        continue;
                    }

                    if (isset($group['expiration'])) {
                        $group['expiration'] = (int) $group['expiration'];
                    }

                    $name = (string) $group['snippet_name'];
                    $existing = $merged[$name] ?? [];
                    $entries = [...$existing['entries'] ?? [], ...$group['entries'] ?? []];

                    $merged[$name] = array_merge($existing, $group);
                    if ($entries !== []) {
                        $merged[$name]['entries'] = $entries;
                    }
                }

                foreach ($merged as $name => $group) {
                    $connection->executeStatement(
                        'INSERT INTO `app_feature` (`id`, `app_id`, `app_name`, `type`, `name`, `payload`, `created_at`)
                         VALUES (:id, :appId, :appName, :type, :name, :payload, :createdAt)
                         ON DUPLICATE KEY UPDATE `app_id` = VALUES(`app_id`), `payload` = VALUES(`payload`)',
                        [
                            'id' => Uuid::randomBytes(),
                            'appId' => $app['id'],
                            'appName' => $app['name'],
                            'type' => 'cookie',
                            'name' => $name,
                            'payload' => json_encode($group, \JSON_THROW_ON_ERROR),
                            'createdAt' => $now,
                        ]
                    );
                }
            }
        });
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'app', 'cookies');
    }
}
