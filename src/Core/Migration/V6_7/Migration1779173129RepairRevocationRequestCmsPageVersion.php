<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
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
        // Re-run a copy of the fixed migration first. It now writes
        // all CMS version columns explicitly with Defaults::LIVE_VERSION.
        $this->fixMigration($connection);

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
        );

        if (!\is_string($pageByteId)) {
            return null;
        }

        return $pageByteId;
    }

    /**
     * @return array{id: string, configuration_value: mixed}|null
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

        $id = $configuration['id'] ?? null;
        if (!\is_string($id)) {
            return null;
        }

        return [
            'id' => $id,
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

    private function fixMigration(Connection $connection): void
    {
        $enLanguageByteId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLanguageByteId = $this->getLanguageIdByLocale($connection, 'de-DE');
        $versionByteId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $cmsPageByteId = $this->createCmsPage($connection, $versionByteId, $enLanguageByteId, $deLanguageByteId);
        $cmsSectionByteId = $this->createCmsSection($connection, $cmsPageByteId, $versionByteId);
        $cmsBlockByteId = $this->createCmsBlock($connection, $cmsSectionByteId, $versionByteId);
        $this->createCmsSlot($connection, $cmsBlockByteId, $versionByteId, $enLanguageByteId, $deLanguageByteId);
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }

    private function createCmsPage(Connection $connection, string $versionByteId, ?string $enLanguageByteId, ?string $deLanguageByteId): string
    {
        $cmsPageByteId = $this->getCmsPageId($connection, $versionByteId);
        if ($cmsPageByteId !== null) {
            return $cmsPageByteId;
        }

        $cmsPageByteId = Uuid::randomBytes();

        $connection->insert(
            'cms_page',
            [
                'id' => $cmsPageByteId,
                'version_id' => $versionByteId,
                'type' => 'page',
                'locked' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        if ($enLanguageByteId !== null) {
            $connection->insert(
                'cms_page_translation',
                [
                    'cms_page_id' => $cmsPageByteId,
                    'cms_page_version_id' => $versionByteId,
                    'language_id' => $enLanguageByteId,
                    'name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($deLanguageByteId !== null && $deLanguageByteId !== $enLanguageByteId) {
            $connection->insert(
                'cms_page_translation',
                [
                    'cms_page_id' => $cmsPageByteId,
                    'cms_page_version_id' => $versionByteId,
                    'language_id' => $deLanguageByteId,
                    'name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['de_name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        return $cmsPageByteId;
    }

    private function createCmsSection(Connection $connection, string $cmsPageByteId, string $versionByteId): string
    {
        $cmsSectionByteId = $this->getCmsSectionId($connection, $cmsPageByteId, $versionByteId);
        if ($cmsSectionByteId !== null) {
            return $cmsSectionByteId;
        }
        $cmsSectionByteId = Uuid::randomBytes();

        $connection->insert(
            'cms_section',
            [
                'id' => $cmsSectionByteId,
                'version_id' => $versionByteId,
                'cms_page_id' => $cmsPageByteId,
                'cms_page_version_id' => $versionByteId,
                'position' => 0,
                'type' => 'default',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        return $cmsSectionByteId;
    }

    private function createCmsBlock(Connection $connection, string $cmsSectionByteId, string $versionByteId): string
    {
        $cmsBlockByteId = $this->getCmsBlockId($connection, $cmsSectionByteId, $versionByteId);
        if ($cmsBlockByteId !== null) {
            return $cmsBlockByteId;
        }
        $cmsBlockByteId = Uuid::randomBytes();

        $connection->insert(
            'cms_block',
            [
                'id' => $cmsBlockByteId,
                'version_id' => $versionByteId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $cmsSectionByteId,
                'cms_section_version_id' => $versionByteId,
                'locked' => 1,
                'position' => 1,
                'type' => 'form',
                'name' => Migration1768545320RevocationRequestCmsForm::CMS_BLOCK_NAME,
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ]
        );

        return $cmsBlockByteId;
    }

    private function createCmsSlot(
        Connection $connection,
        string $cmsBlockByteId,
        string $versionByteId,
        ?string $enLanguageByteId,
        ?string $deLanguageByteId
    ): void {
        $cmsSlotByteId = $this->getCmsSlotId($connection, $cmsBlockByteId, $versionByteId);
        if ($cmsSlotByteId !== null) {
            return;
        }
        $cmsSlotByteId = Uuid::randomBytes();

        $connection->insert(
            'cms_slot',
            [
                'id' => $cmsSlotByteId,
                'locked' => 1,
                'cms_block_id' => $cmsBlockByteId,
                'cms_block_version_id' => $versionByteId,
                'type' => 'form',
                'slot' => 'content',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'version_id' => $versionByteId,
            ]
        );

        if ($enLanguageByteId !== null) {
            $connection->insert(
                'cms_slot_translation',
                [
                    'cms_slot_id' => $cmsSlotByteId,
                    'cms_slot_version_id' => $versionByteId,
                    'language_id' => $enLanguageByteId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'config' => json_encode([
                        'type' => ['source' => 'static', 'value' => Migration1768545320RevocationRequestCmsForm::CMS_SLOT_TYPE],
                        'mailReceiver' => ['source' => 'static', 'value' => []],
                        'confirmationText' => ['source' => 'static', 'value' => ''],
                    ], \JSON_THROW_ON_ERROR),
                ]
            );
        }

        if ($deLanguageByteId !== null && $deLanguageByteId !== $enLanguageByteId) {
            $connection->insert(
                'cms_slot_translation',
                [
                    'cms_slot_id' => $cmsSlotByteId,
                    'cms_slot_version_id' => $versionByteId,
                    'language_id' => $deLanguageByteId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'config' => json_encode([
                        'type' => ['source' => 'static', 'value' => Migration1768545320RevocationRequestCmsForm::CMS_SLOT_TYPE],
                        'mailReceiver' => ['source' => 'static', 'value' => []],
                        'confirmationText' => ['source' => 'static', 'value' => ''],
                    ], \JSON_THROW_ON_ERROR),
                ]
            );
        }
    }

    private function getCmsPageId(Connection $connection, string $versionByteId): ?string
    {
        $sql = <<<'SQL'
SELECT `id` 
FROM `cms_page` AS `page`
INNER JOIN `cms_page_translation` AS `page_translation` ON `page`.`id` = `page_translation`.`cms_page_id`
    AND `page`.`version_id` = `page_translation`.`cms_page_version_id`
WHERE `page`.`version_id` = :versionId
    AND page_translation.name = :name
SQL;

        $cmsPageByteId = $connection->executeQuery(
            $sql,
            [
                'name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'],
                'versionId' => $versionByteId,
            ]
        )->fetchOne();

        if (!\is_string($cmsPageByteId)) {
            return null;
        }

        return $cmsPageByteId;
    }

    private function getCmsSectionId(Connection $connection, string $cmsPageByteId, string $versionByteId): ?string
    {
        $cmsSectionByteId = $connection->executeQuery(
            'SELECT `id` FROM `cms_section` WHERE `cms_page_id` = :cmsPageId AND `cms_page_version_id` = :versionId AND `version_id` = :versionId',
            [
                'cmsPageId' => $cmsPageByteId,
                'versionId' => $versionByteId,
            ]
        )->fetchOne();

        if (!\is_string($cmsSectionByteId)) {
            return null;
        }

        return $cmsSectionByteId;
    }

    private function getCmsBlockId(Connection $connection, string $cmsSectionByteId, string $versionByteId): ?string
    {
        $cmsBlockByteId = $connection->executeQuery(
            'SELECT `id` FROM `cms_block` WHERE `name` = :cmsBlockName AND `cms_section_id` = :cmsSectionId AND `cms_section_version_id` = :versionId AND `version_id` = :versionId',
            [
                'cmsBlockName' => Migration1768545320RevocationRequestCmsForm::CMS_BLOCK_NAME,
                'cmsSectionId' => $cmsSectionByteId,
                'versionId' => $versionByteId,
            ]
        )->fetchOne();

        if (!\is_string($cmsBlockByteId)) {
            return null;
        }

        return $cmsBlockByteId;
    }

    private function getCmsSlotId(Connection $connection, string $cmsBlockByteId, string $versionByteId): ?string
    {
        $cmsSlotByteId = $connection->executeQuery(
            'SELECT `id` FROM `cms_slot` WHERE `cms_block_id` = :cmsBlockId AND `cms_block_version_id` = :versionId AND `version_id` = :versionId',
            [
                'cmsBlockId' => $cmsBlockByteId,
                'versionId' => $versionByteId,
            ]
        )->fetchOne();

        if (!\is_string($cmsSlotByteId)) {
            return null;
        }

        return $cmsSlotByteId;
    }
}
