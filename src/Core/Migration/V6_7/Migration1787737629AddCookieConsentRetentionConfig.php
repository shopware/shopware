<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Seeds the retention period for cookie consent evidence.
 *
 * The default in the config XML only prefills the admin form once a value exists,
 * so without a stored row the field renders empty. An empty retention field reads
 * as "kept forever", while the cleanup actually applies its 120 day fallback.
 *
 * @internal
 */
#[Package('framework')]
class Migration1787737629AddCookieConsentRetentionConfig extends MigrationStep
{
    private const CONFIG_KEY = 'core.cookieConsentRetention.days';

    public function getCreationTimestamp(): int
    {
        return 1787737629;
    }

    public function update(Connection $connection): void
    {
        $exists = $connection->fetchOne(
            'SELECT id FROM system_config WHERE configuration_key = :key',
            ['key' => self::CONFIG_KEY],
        );

        // Never overwrite: an operator who already chose a retention period keeps it
        if ($exists) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => '{"_value": 120}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
