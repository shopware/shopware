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
class Migration1784053323MigrateAppModulesToAppFeature extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784053323;
    }

    public function update(Connection $connection): void
    {
        $apps = $connection->fetchAllAssociative(
            'SELECT `id`, `name`, `modules`, `main_module` FROM `app` WHERE (`modules` IS NOT NULL AND `modules` != \'[]\') OR `main_module` IS NOT NULL'
        );

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($apps as $app) {
            $modules = json_decode((string) ($app['modules'] ?? '[]'), true);
            $modules = \is_array($modules) ? array_values($modules) : [];

            $mainModule = null;
            if ($app['main_module'] !== null) {
                $decoded = json_decode((string) $app['main_module'], true);
                $mainModule = \is_array($decoded) ? $decoded : null;
            }

            if ($modules === [] && $mainModule === null) {
                continue;
            }

            $connection->insert('app_feature', [
                'id' => Uuid::randomBytes(),
                'app_id' => $app['id'],
                'app_name' => $app['name'],
                'type' => 'module',
                'name' => 'admin',
                'payload' => json_encode(['modules' => $modules, 'mainModule' => $mainModule], \JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // app admin modules are stored in the generic app_feature table now

        // JSON_VALID check constraints reference the columns, so they must be dropped first
        foreach (['json.app.modules', 'json.app.main_module'] as $constraint) {
            try {
                $connection->executeStatement(\sprintf('ALTER TABLE `app` DROP CHECK `%s`', $constraint));
            } catch (\Throwable) {
                // constraint may already be gone on some installations
            }
        }

        $this->dropColumnIfExists($connection, 'app', 'modules');
        $this->dropColumnIfExists($connection, 'app', 'main_module');
    }
}
