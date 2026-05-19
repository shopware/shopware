<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Repairs revocation CMS page assignments for systems where the original
 * revocation CMS page migration ran with wrong database defaults for versioned
 * CMS tables.
 *
 * Affected systems can contain the shipped revocation page in a non-live
 * version. The page then exists in the database, but the administration cannot
 * use it as the configured shop page. This migration first ensures that the
 * fixed CMS page migration created a live-version page, then repairs only the
 * global system config when it is missing or points to a non-live/missing page.
 *
 * Valid custom live-version assignments and sales-channel-specific assignments
 * are intentionally left untouched.
 *
 * @internal
 */
#[Package('after-sales')]
class Migration1779173129RepairRevocationRequestCmsPageVersion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779173129;
    }

    public function update(Connection $connection): void
    {
        // Re-run the fixed migration first. It now writes
        // all CMS version columns explicitly with Defaults::LIVE_VERSION.
        (new Migration1768545320RevocationRequestCmsForm())->update($connection);

        $liveRevocationPageByteId = $this->getLiveRevocationPageId($connection);
        if ($liveRevocationPageByteId === null) {
            return;
        }

        $configuration = $this->getGlobalRevocationPageConfiguration($connection);
        if ($configuration === null) {
            // The original assignment migration wrote a global config entry. If
            // only sales-channel-specific entries exist, assume customized data.
            if ($this->hasAnyRevocationPageConfiguration($connection)) {
                return;
            }

            $this->insertGlobalRevocationPageConfiguration($connection, $liveRevocationPageByteId);
            $this->disableGlobalRevocationButtonIfMissing($connection);

            return;
        }

        $configuredPageId = $this->extractCmsPageId($configuration['configuration_value'] ?? null);
        if ($configuredPageId !== null && $this->cmsPageExistsInLiveVersion($connection, $configuredPageId)) {
            return;
        }

        // At this point the global config is missing a usable live-version page
        // reference, so it is safe to point it to the repaired default page.
        $configurationId = $configuration['id'];
        if (!\is_string($configurationId)) {
            return;
        }

        $connection->update(
            'system_config',
            [
                'configuration_value' => $this->createPageConfigurationValue($liveRevocationPageByteId),
                'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['id' => $configurationId]
        );

        $this->disableGlobalRevocationButtonIfMissing($connection);
    }

    private function getLiveRevocationPageId(Connection $connection): ?string
    {
        $pageByteId = $connection->fetchOne(
            <<<'SQL'
SELECT `page`.`id`
FROM `cms_page` AS `page`
INNER JOIN `cms_page_translation` AS `page_translation`
    ON `page_translation`.`cms_page_id` = `page`.`id`
    AND `page_translation`.`cms_page_version_id` = `page`.`version_id`
WHERE `page`.`version_id` = :versionId
    AND `page_translation`.`name` = :name
LIMIT 1
SQL,
            [
                'name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'],
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['versionId' => ParameterType::BINARY]
        );

        if (!\is_string($pageByteId)) {
            return null;
        }

        return $pageByteId;
    }

    /**
     * @return array{id: mixed, configuration_value: mixed}|null
     */
    private function getGlobalRevocationPageConfiguration(Connection $connection): ?array
    {
        $configuration = $connection->fetchAssociative(
            'SELECT `id`, `configuration_value` FROM `system_config` WHERE `configuration_key` = :configKey AND `sales_channel_id` IS NULL LIMIT 1',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY]
        );

        if (!\is_array($configuration)) {
            return null;
        }

        return [
            'id' => $configuration['id'] ?? null,
            'configuration_value' => $configuration['configuration_value'] ?? null,
        ];
    }

    private function hasAnyRevocationPageConfiguration(Connection $connection): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :configKey LIMIT 1',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY]
        );
    }

    private function extractCmsPageId(mixed $configurationValue): ?string
    {
        if (!\is_string($configurationValue)) {
            return null;
        }

        $decoded = json_decode($configurationValue, true);
        if (!\is_array($decoded)) {
            return null;
        }

        $cmsPageId = $decoded['_value'] ?? null;
        if (!\is_string($cmsPageId) || !Uuid::isValid($cmsPageId)) {
            return null;
        }

        return $cmsPageId;
    }

    private function cmsPageExistsInLiveVersion(Connection $connection, string $cmsPageId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `cms_page` WHERE `id` = :id AND `version_id` = :versionId LIMIT 1',
            [
                'id' => Uuid::fromHexToBytes($cmsPageId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'id' => ParameterType::BINARY,
                'versionId' => ParameterType::BINARY,
            ]
        );
    }

    private function insertGlobalRevocationPageConfiguration(Connection $connection, string $pageByteId): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY,
            'configuration_value' => $this->createPageConfigurationValue($pageByteId),
            'sales_channel_id' => null,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function disableGlobalRevocationButtonIfMissing(Connection $connection): void
    {
        $configExists = (bool) $connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :configKey AND `sales_channel_id` IS NULL LIMIT 1',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_BUTTON_CONFIG_KEY]
        );

        if ($configExists) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_BUTTON_CONFIG_KEY,
            'configuration_value' => '{"_value": false}',
            'sales_channel_id' => null,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createPageConfigurationValue(string $pageByteId): string
    {
        return json_encode(['_value' => Uuid::fromBytesToHex($pageByteId)], \JSON_THROW_ON_ERROR);
    }
}
