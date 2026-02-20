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
class Migration1770621743CopyUsageDataConsentState extends MigrationStep
{
    private const CONFIG_KEY = 'core.usageData.consentState';
    private const CONSENT_NAME = 'backend_data';
    private const CONSENT_IDENTIFIER = 'system';

    public function getCreationTimestamp(): int
    {
        return 1770621743;
    }

    public function update(Connection $connection): void
    {
        $rawConfig = $connection->fetchAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::CONFIG_KEY]
        );

        if ($rawConfig === false) {
            return;
        }

        /** @var array{_value?: string} $config */
        $config = json_decode($rawConfig['configuration_value'], true, flags: \JSON_THROW_ON_ERROR);

        $value = $config['_value'] ?? null;
        if (!\is_string($value)) {
            return;
        }

        if (!\in_array($value, ['accepted', 'revoked'], true)) {
            return;
        }

        $connection->executeStatement(
            'INSERT IGNORE INTO consent_state (id, name, identifier, state, actor, updated_at)
            VALUES (:id, :name, :identifier, :state, :actor, :updatedAt)',
            [
                'id' => Uuid::randomBytes(),
                'name' => self::CONSENT_NAME,
                'identifier' => self::CONSENT_IDENTIFIER,
                'state' => $value,
                'actor' => 'migration',
                'updatedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['id' => 'binary']
        );
    }
}
