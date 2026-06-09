<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentScope\System;

/**
 * @internal
 */
#[Package('framework')]
class Migration1778050000MigrateServicesConsentToNativeConsent extends MigrationStep
{
    private const LEGACY_CONFIG_KEY = 'core.services.permissionsConsent';

    private const SERVICE_CONSENT_NAME = 'service_consent';

    public function getCreationTimestamp(): int
    {
        return 1778050000;
    }

    public function update(Connection $connection): void
    {
        $legacyConfig = $connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL LIMIT 1',
            ['key' => self::LEGACY_CONFIG_KEY]
        );

        if (!\is_string($legacyConfig)) {
            return;
        }

        $existingState = $connection->fetchOne(
            'SELECT 1 FROM consent_state WHERE name = :name AND identifier = :identifier LIMIT 1',
            ['name' => self::SERVICE_CONSENT_NAME, 'identifier' => System::NAME]
        );

        if ($existingState === false) {
            $decoded = json_decode($legacyConfig, true);

            if (
                \is_array($decoded)
                && isset($decoded[ConfigJsonField::STORAGE_KEY])
                && \is_array($decoded[ConfigJsonField::STORAGE_KEY])
                && isset(
                    $decoded[ConfigJsonField::STORAGE_KEY]['revision'],
                    $decoded[ConfigJsonField::STORAGE_KEY]['consentingUserId'],
                    $decoded[ConfigJsonField::STORAGE_KEY]['grantedAt'],
                )
            ) {
                try {
                    $grantedAt = new \DateTimeImmutable((string) $decoded[ConfigJsonField::STORAGE_KEY]['grantedAt']);
                } catch (\Throwable) {
                    $grantedAt = null;
                }

                if ($grantedAt !== null) {
                    $connection->insert('consent_state', [
                        'id' => Uuid::randomBytes(),
                        'name' => self::SERVICE_CONSENT_NAME,
                        'identifier' => System::NAME,
                        'state' => 'accepted',
                        'actor' => (string) $decoded[ConfigJsonField::STORAGE_KEY]['consentingUserId'],
                        'revision' => (string) $decoded[ConfigJsonField::STORAGE_KEY]['revision'],
                        'updated_at' => $grantedAt->format('Y-m-d H:i:s.v'),
                    ], [
                        'id' => 'binary',
                    ]);
                }
            }
        }

        $connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::LEGACY_CONFIG_KEY]
        );
    }
}
