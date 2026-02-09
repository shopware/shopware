<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

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
        $this->addSyncTriggers($connection);

        $rawConfig = $connection->fetchAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::CONFIG_KEY]
        );

        if ($rawConfig === false) {
            return;
        }

        /** @var array{_value?: string} $config */
        $config = json_decode($rawConfig['configuration_value'], true, 512, \JSON_THROW_ON_ERROR);

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

    private function addSyncTriggers(Connection $connection): void
    {
        $this->removeTrigger($connection, 'usage_data_system_config_consent_insert');
        $this->removeTrigger($connection, 'usage_data_system_config_consent_update');
        $this->removeTrigger($connection, 'usage_data_consent_state_insert');
        $this->removeTrigger($connection, 'usage_data_consent_state_update');
        $this->removeTrigger($connection, 'usage_data_consent_state_delete');

        $this->createTrigger($connection, '
            CREATE TRIGGER usage_data_system_config_consent_insert AFTER INSERT ON system_config
            FOR EACH ROW BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    IF NEW.configuration_key = :configKey AND NEW.sales_channel_id IS NULL THEN
                        SET @consent_state = JSON_UNQUOTE(JSON_EXTRACT(NEW.configuration_value, \'$._value\'));
                        SET @TRIGGER_DISABLED = 1;
                        IF @consent_state IN ("accepted", "revoked") THEN
                            INSERT INTO consent_state (id, name, identifier, state, actor, updated_at)
                            VALUES (UNHEX(REPLACE(UUID(),"-","")), :consentName, :identifier, @consent_state, "system_config", NOW(3))
                            ON DUPLICATE KEY UPDATE
                                state = @consent_state,
                                actor = "system_config",
                                updated_at = NOW(3);
                        ELSE
                            DELETE FROM consent_state WHERE name = :consentName AND identifier = :identifier;
                        END IF;
                        SET @TRIGGER_DISABLED = 0;
                    END IF;
                END IF;
            END;
        ', [
            'configKey' => self::CONFIG_KEY,
            'consentName' => self::CONSENT_NAME,
            'identifier' => self::CONSENT_IDENTIFIER,
        ]);

        $this->createTrigger($connection, '
            CREATE TRIGGER usage_data_system_config_consent_update AFTER UPDATE ON system_config
            FOR EACH ROW BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    IF NEW.configuration_key = :configKey AND NEW.sales_channel_id IS NULL THEN
                        SET @consent_state = JSON_UNQUOTE(JSON_EXTRACT(NEW.configuration_value, \'$._value\'));
                        SET @TRIGGER_DISABLED = 1;
                        IF @consent_state IN ("accepted", "revoked") THEN
                            INSERT INTO consent_state (id, name, identifier, state, actor, updated_at)
                            VALUES (UNHEX(REPLACE(UUID(),"-","")), :consentName, :identifier, @consent_state, "system_config", NOW(3))
                            ON DUPLICATE KEY UPDATE
                                state = @consent_state,
                                actor = "system_config",
                                updated_at = NOW(3);
                        ELSE
                            DELETE FROM consent_state WHERE name = :consentName AND identifier = :identifier;
                        END IF;
                        SET @TRIGGER_DISABLED = 0;
                    END IF;
                END IF;
            END;
        ', [
            'configKey' => self::CONFIG_KEY,
            'consentName' => self::CONSENT_NAME,
            'identifier' => self::CONSENT_IDENTIFIER,
        ]);

        $this->createTrigger($connection, '
            CREATE TRIGGER usage_data_consent_state_insert AFTER INSERT ON consent_state
            FOR EACH ROW BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    IF NEW.name = :consentName AND NEW.identifier = :identifier THEN
                        SET @TRIGGER_DISABLED = 1;
                        IF NEW.state IN ("accepted", "revoked") THEN
                            INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at, updated_at)
                            VALUES (UNHEX(REPLACE(UUID(),"-","")), :configKey, JSON_OBJECT(\'_value\', NEW.state), NULL, NOW(3), NOW(3))
                            ON DUPLICATE KEY UPDATE
                                configuration_value = JSON_OBJECT(\'_value\', NEW.state),
                                updated_at = NOW(3);
                        ELSE
                            DELETE FROM system_config WHERE configuration_key = :configKey AND sales_channel_id IS NULL;
                        END IF;
                        SET @TRIGGER_DISABLED = 0;
                    END IF;
                END IF;
            END;
        ', [
            'configKey' => self::CONFIG_KEY,
            'consentName' => self::CONSENT_NAME,
            'identifier' => self::CONSENT_IDENTIFIER,
        ]);

        $this->createTrigger($connection, '
            CREATE TRIGGER usage_data_consent_state_update AFTER UPDATE ON consent_state
            FOR EACH ROW BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    IF NEW.name = :consentName AND NEW.identifier = :identifier THEN
                        SET @TRIGGER_DISABLED = 1;
                        IF NEW.state IN ("accepted", "revoked") THEN
                            INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at, updated_at)
                            VALUES (UNHEX(REPLACE(UUID(),"-","")), :configKey, JSON_OBJECT(\'_value\', NEW.state), NULL, NOW(3), NOW(3))
                            ON DUPLICATE KEY UPDATE
                                configuration_value = JSON_OBJECT(\'_value\', NEW.state),
                                updated_at = NOW(3);
                        ELSE
                            DELETE FROM system_config WHERE configuration_key = :configKey AND sales_channel_id IS NULL;
                        END IF;
                        SET @TRIGGER_DISABLED = 0;
                    END IF;
                END IF;
            END;
        ', [
            'configKey' => self::CONFIG_KEY,
            'consentName' => self::CONSENT_NAME,
            'identifier' => self::CONSENT_IDENTIFIER,
        ]);

        $this->createTrigger($connection, '
            CREATE TRIGGER usage_data_consent_state_delete AFTER DELETE ON consent_state
            FOR EACH ROW BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    IF OLD.name = :consentName AND OLD.identifier = :identifier THEN
                        SET @TRIGGER_DISABLED = 1;
                        DELETE FROM system_config WHERE configuration_key = :configKey AND sales_channel_id IS NULL;
                        SET @TRIGGER_DISABLED = 0;
                    END IF;
                END IF;
            END;
        ', [
            'configKey' => self::CONFIG_KEY,
            'consentName' => self::CONSENT_NAME,
            'identifier' => self::CONSENT_IDENTIFIER,
        ]);
    }
}
