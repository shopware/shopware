<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Seeds the default for the switch that turns cookie consent logging on or off.
 *
 * The default in the config XML only fills the admin form, it never reaches
 * `system_config`, so it has to be written here for the value to exist.
 *
 * @internal
 */
#[Package('framework')]
class Migration1787727107AddCookieConsentLogEnabledConfig extends MigrationStep
{
    private const CONFIG_KEY = 'core.cookieConsent.logEnabled';

    public function getCreationTimestamp(): int
    {
        return 1787727107;
    }

    public function update(Connection $connection): void
    {
        $exists = $connection->fetchOne(
            'SELECT id FROM system_config WHERE configuration_key = :key',
            ['key' => self::CONFIG_KEY],
        );

        // Never overwrite: an operator who already switched logging off must keep that choice
        if ($exists) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => '{"_value": true}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
