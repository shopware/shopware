<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Structs\MailTemplateCreateStruct;
use Shopware\Core\Migration\Structs\MailTemplateTypeCreateStruct;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1781862820RepairRevocationRequestTranslations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1781862820;
    }

    public function update(Connection $connection): void
    {
        $this->repairRevocationRequestMailTemplates($connection);

        $liveRevocationPageByteId = $this->repairRevocationRequestCmsPage($connection);

        $this->repairRevocationRequestCmsPageConfiguration($connection, $liveRevocationPageByteId);
    }

    private function repairRevocationRequestMailTemplates(Connection $connection): void
    {
        $merchantType = new MailTemplateTypeCreateStruct(
            MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT,
            'Revocation request received',
            'Widerrufsantrag erhalten',
        );

        $merchantTemplate = new MailTemplateCreateStruct(
            Migration1768545319RevocationRequestMailTemplate::MERCHANT_DIRECTORY,
            'Revocation request received',
            'Widerrufsantrag erhalten',
            'Received revocation request from customer',
            'Widerrufsantrag vom Kunden erhalten',
            '{{ salesChannel.translated.name }}',
            '{{ salesChannel.translated.name }}',
        );

        $this->repairMailTemplate($connection, $merchantType, $merchantTemplate);

        $customerType = new MailTemplateTypeCreateStruct(
            MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER,
            'Revocation request requested',
            'Widerrufsantrag gestellt',
        );

        $customerTemplate = new MailTemplateCreateStruct(
            Migration1768545319RevocationRequestMailTemplate::CUSTOMER_DIRECTORY,
            'Revocation request sent',
            'Widerrufsantrag gesendet',
            'Confirmation receipt of customers revocation request',
            'Empfangsbestätigung für Widerrufsantrag des Kunden',
            '{{ salesChannel.translated.name }}',
            '{{ salesChannel.translated.name }}',
        );

        $this->repairMailTemplate($connection, $customerType, $customerTemplate);
    }

    private function repairMailTemplate(Connection $connection, MailTemplateTypeCreateStruct $mailTemplateType, MailTemplateCreateStruct $mailTemplate): void
    {
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId($connection, $mailTemplateType->getTechnicalName());
        if ($mailTemplateTypeByteId === null) {
            $mailTemplateTypeByteId = Uuid::randomBytes();

            $connection->insert('mail_template_type', [
                'id' => $mailTemplateTypeByteId,
                'technical_name' => $mailTemplateType->getTechnicalName(),
                'available_entities' => \json_encode($mailTemplateType->getAvailableEntities(), \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $mailTemplateByteId = $this->getUnmodifiedSystemDefaultMailTemplateId($connection, $mailTemplateTypeByteId);
        if ($mailTemplateByteId === null) {
            if ($this->hasSystemDefaultMailTemplate($connection, $mailTemplateTypeByteId)) {
                return;
            }

            $mailTemplateByteId = Uuid::randomBytes();

            $connection->insert('mail_template', [
                'id' => $mailTemplateByteId,
                'mail_template_type_id' => $mailTemplateTypeByteId,
                'system_default' => $mailTemplate->isSystemDefault(),
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        foreach ($this->getLanguageIdsByLocalePrefix($connection, 'de') as $deLanguageByteId) {
            $this->repairMailTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $deLanguageByteId, $mailTemplateType->getDeName());
            $this->repairMailTemplateTranslation($connection, $mailTemplateByteId, $deLanguageByteId, [
                'sender_name' => $mailTemplate->getDeSenderName(),
                'subject' => $mailTemplate->getDeSubject(),
                'description' => $mailTemplate->getDeDescription(),
                'content_html' => $mailTemplate->getDeHtml(),
                'content_plain' => $mailTemplate->getDePlain(),
            ]);
        }

        foreach ($this->getLanguageIdsByLocalePrefix($connection, 'de', true) as $enLanguageByteId) {
            $this->repairMailTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $enLanguageByteId, $mailTemplateType->getEnName());
            $this->repairMailTemplateTranslation($connection, $mailTemplateByteId, $enLanguageByteId, [
                'sender_name' => $mailTemplate->getEnSenderName(),
                'subject' => $mailTemplate->getEnSubject(),
                'description' => $mailTemplate->getEnDescription(),
                'content_html' => $mailTemplate->getEnHtml(),
                'content_plain' => $mailTemplate->getEnPlain(),
            ]);
        }
    }

    private function getMailTemplateTypeId(Connection $connection, string $technicalName): ?string
    {
        $mailTemplateTypeByteId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName LIMIT 1',
            ['technicalName' => $technicalName],
        );

        if (!\is_string($mailTemplateTypeByteId)) {
            return null;
        }

        return $mailTemplateTypeByteId;
    }

    private function getUnmodifiedSystemDefaultMailTemplateId(Connection $connection, string $mailTemplateTypeByteId): ?string
    {
        $mailTemplateByteId = $connection->fetchOne(
            <<<'SQL'
SELECT `id`
FROM `mail_template`
WHERE `mail_template_type_id` = :mailTemplateTypeId
    AND `system_default` = 1
    AND `updated_at` IS NULL
ORDER BY `created_at` ASC, `id` ASC
LIMIT 1
SQL,
            ['mailTemplateTypeId' => $mailTemplateTypeByteId],
        );

        if (!\is_string($mailTemplateByteId)) {
            return null;
        }

        return $mailTemplateByteId;
    }

    private function hasSystemDefaultMailTemplate(Connection $connection, string $mailTemplateTypeByteId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND `system_default` = 1 LIMIT 1',
            ['mailTemplateTypeId' => $mailTemplateTypeByteId],
        );
    }

    private function repairMailTemplateTypeTranslation(Connection $connection, string $mailTemplateTypeByteId, string $languageByteId, string $name): void
    {
        $translation = $connection->fetchAssociative(
            'SELECT `updated_at` FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :mailTemplateTypeId AND `language_id` = :languageId LIMIT 1',
            ['languageId' => $languageByteId, 'mailTemplateTypeId' => $mailTemplateTypeByteId],
        );

        if (\is_array($translation)) {
            if ($translation['updated_at'] !== null) {
                return;
            }

            $connection->update(
                'mail_template_type_translation',
                ['name' => $name],
                ['language_id' => $languageByteId, 'mail_template_type_id' => $mailTemplateTypeByteId],
            );

            return;
        }

        $connection->insert('mail_template_type_translation', [
            'mail_template_type_id' => $mailTemplateTypeByteId,
            'language_id' => $languageByteId,
            'name' => $name,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @param array{sender_name: string, subject: string, description: string, content_html: string, content_plain: string} $translationData
     */
    private function repairMailTemplateTranslation(Connection $connection, string $mailTemplateByteId, string $languageByteId, array $translationData): void
    {
        $translation = $connection->fetchAssociative(
            'SELECT `updated_at` FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId AND `language_id` = :languageId LIMIT 1',
            ['languageId' => $languageByteId, 'mailTemplateId' => $mailTemplateByteId],
        );

        if (\is_array($translation)) {
            if ($translation['updated_at'] !== null) {
                return;
            }

            $connection->update(
                'mail_template_translation',
                $translationData,
                ['language_id' => $languageByteId, 'mail_template_id' => $mailTemplateByteId],
            );

            return;
        }

        $connection->insert('mail_template_translation', [
            'mail_template_id' => $mailTemplateByteId,
            'language_id' => $languageByteId,
            ...$translationData,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function repairRevocationRequestCmsPage(Connection $connection): string
    {
        $deLanguageByteIds = $this->getLanguageIdsByLocalePrefix($connection, 'de');
        $enLanguageByteIds = $this->getLanguageIdsByLocalePrefix($connection, 'de', true);
        $versionByteId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $cmsPageByteId = $this->createCmsPage($connection, $versionByteId, $enLanguageByteIds, $deLanguageByteIds);
        $cmsSectionByteId = $this->createCmsSection($connection, $cmsPageByteId, $versionByteId);
        $cmsBlockByteId = $this->createCmsBlock($connection, $cmsSectionByteId, $versionByteId);
        $this->createCmsSlot($connection, $cmsBlockByteId, $versionByteId, $enLanguageByteIds, $deLanguageByteIds);

        return $cmsPageByteId;
    }

    /**
     * @param list<string> $enLanguageByteIds
     * @param list<string> $deLanguageByteIds
     */
    private function createCmsPage(Connection $connection, string $versionByteId, array $enLanguageByteIds, array $deLanguageByteIds): string
    {
        $cmsPageByteId = $this->getCmsPageId($connection, $versionByteId);
        if ($cmsPageByteId === null) {
            $cmsPageByteId = Uuid::randomBytes();

            $connection->insert('cms_page', [
                'id' => $cmsPageByteId,
                'version_id' => $versionByteId,
                'type' => 'page',
                'locked' => 1,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $this->repairCmsPageTranslations($connection, $cmsPageByteId, $versionByteId, $enLanguageByteIds, $deLanguageByteIds);

        return $cmsPageByteId;
    }

    private function createCmsSection(Connection $connection, string $cmsPageByteId, string $versionByteId): string
    {
        $cmsSectionByteId = $connection->fetchOne(
            'SELECT `id` FROM `cms_section` WHERE `cms_page_id` = :cmsPageId AND `cms_page_version_id` = :versionId AND `version_id` = :versionId LIMIT 1',
            ['cmsPageId' => $cmsPageByteId, 'versionId' => $versionByteId],
        );

        if (\is_string($cmsSectionByteId)) {
            return $cmsSectionByteId;
        }

        $cmsSectionByteId = Uuid::randomBytes();

        $connection->insert('cms_section', [
            'id' => $cmsSectionByteId,
            'version_id' => $versionByteId,
            'cms_page_id' => $cmsPageByteId,
            'cms_page_version_id' => $versionByteId,
            'position' => 0,
            'type' => 'default',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $cmsSectionByteId;
    }

    private function createCmsBlock(Connection $connection, string $cmsSectionByteId, string $versionByteId): string
    {
        $cmsBlockByteId = $connection->fetchOne(
            'SELECT `id` FROM `cms_block` WHERE `name` = :cmsBlockName AND `cms_section_id` = :cmsSectionId AND `cms_section_version_id` = :versionId AND `version_id` = :versionId LIMIT 1',
            [
                'cmsBlockName' => Migration1768545320RevocationRequestCmsForm::CMS_BLOCK_NAME,
                'cmsSectionId' => $cmsSectionByteId,
                'versionId' => $versionByteId,
            ],
        );

        if (\is_string($cmsBlockByteId)) {
            return $cmsBlockByteId;
        }

        $cmsBlockByteId = Uuid::randomBytes();

        $connection->insert('cms_block', [
            'id' => $cmsBlockByteId,
            'version_id' => $versionByteId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
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
        ]);

        return $cmsBlockByteId;
    }

    /**
     * @param list<string> $enLanguageByteIds
     * @param list<string> $deLanguageByteIds
     */
    private function createCmsSlot(Connection $connection, string $cmsBlockByteId, string $versionByteId, array $enLanguageByteIds, array $deLanguageByteIds): void
    {
        $cmsSlotByteId = $connection->fetchOne(
            'SELECT `id` FROM `cms_slot` WHERE `cms_block_id` = :cmsBlockId AND `cms_block_version_id` = :versionId AND `version_id` = :versionId LIMIT 1',
            ['cmsBlockId' => $cmsBlockByteId, 'versionId' => $versionByteId],
        );

        if (!\is_string($cmsSlotByteId)) {
            $cmsSlotByteId = Uuid::randomBytes();

            $connection->insert('cms_slot', [
                'id' => $cmsSlotByteId,
                'locked' => 1,
                'cms_block_id' => $cmsBlockByteId,
                'cms_block_version_id' => $versionByteId,
                'type' => 'form',
                'slot' => 'content',
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'version_id' => $versionByteId,
            ]);
        }

        foreach ([...$enLanguageByteIds, ...$deLanguageByteIds] as $languageByteId) {
            $this->repairCmsSlotTranslation($connection, $cmsSlotByteId, $versionByteId, $languageByteId);
        }
    }

    /**
     * @param list<string> $enLanguageByteIds
     * @param list<string> $deLanguageByteIds
     */
    private function repairCmsPageTranslations(Connection $connection, string $cmsPageByteId, string $versionByteId, array $enLanguageByteIds, array $deLanguageByteIds): void
    {
        foreach ($enLanguageByteIds as $enLanguageByteId) {
            $this->repairCmsPageTranslation($connection, $cmsPageByteId, $versionByteId, $enLanguageByteId, Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name']);
        }

        foreach ($deLanguageByteIds as $deLanguageByteId) {
            $this->repairCmsPageTranslation($connection, $cmsPageByteId, $versionByteId, $deLanguageByteId, Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['de_name']);
        }
    }

    private function repairCmsPageTranslation(Connection $connection, string $cmsPageByteId, string $versionByteId, string $languageByteId, string $name): void
    {
        $translation = $connection->fetchAssociative(
            'SELECT `updated_at` FROM `cms_page_translation` WHERE `cms_page_id` = :cmsPageId AND `cms_page_version_id` = :versionId AND `language_id` = :languageId LIMIT 1',
            [
                'cmsPageId' => $cmsPageByteId,
                'languageId' => $languageByteId,
                'versionId' => $versionByteId,
            ],
        );

        if (\is_array($translation)) {
            if ($translation['updated_at'] !== null) {
                return;
            }

            $connection->update(
                'cms_page_translation',
                ['name' => $name],
                [
                    'cms_page_id' => $cmsPageByteId,
                    'cms_page_version_id' => $versionByteId,
                    'language_id' => $languageByteId,
                ],
            );

            return;
        }

        $connection->insert('cms_page_translation', [
            'cms_page_id' => $cmsPageByteId,
            'cms_page_version_id' => $versionByteId,
            'language_id' => $languageByteId,
            'name' => $name,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function repairCmsSlotTranslation(Connection $connection, string $cmsSlotByteId, string $versionByteId, string $languageByteId): void
    {
        if ((bool) $connection->fetchOne(
            'SELECT 1 FROM `cms_slot_translation` WHERE `cms_slot_id` = :cmsSlotId AND `cms_slot_version_id` = :versionId AND `language_id` = :languageId LIMIT 1',
            [
                'cmsSlotId' => $cmsSlotByteId,
                'languageId' => $languageByteId,
                'versionId' => $versionByteId,
            ],
        )) {
            return;
        }

        $connection->insert('cms_slot_translation', [
            'cms_slot_id' => $cmsSlotByteId,
            'cms_slot_version_id' => $versionByteId,
            'language_id' => $languageByteId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'config' => $this->createCmsSlotConfig(),
        ]);
    }

    private function getCmsPageId(Connection $connection, string $versionByteId): ?string
    {
        $cmsPageByteId = $this->getCmsPageIdByRevocationRequestSlot($connection, $versionByteId);
        if ($cmsPageByteId !== null) {
            return $cmsPageByteId;
        }

        $cmsPageByteId = $connection->fetchOne(
            <<<'SQL'
SELECT `page`.`id`
FROM `cms_page` AS `page`
INNER JOIN `cms_page_translation` AS `page_translation`
    ON `page_translation`.`cms_page_id` = `page`.`id`
    AND `page_translation`.`cms_page_version_id` = `page`.`version_id`
WHERE `page`.`version_id` = :versionId
    AND (`page_translation`.`name` = :enName OR `page_translation`.`name` = :deName)
LIMIT 1
SQL,
            [
                'deName' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['de_name'],
                'enName' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'],
                'versionId' => $versionByteId,
            ],
        );

        if (!\is_string($cmsPageByteId)) {
            return null;
        }

        return $cmsPageByteId;
    }

    private function getCmsPageIdByRevocationRequestSlot(Connection $connection, string $versionByteId): ?string
    {
        $cmsPageByteId = $connection->fetchOne(
            <<<'SQL'
SELECT `page`.`id`
FROM `cms_page` AS `page`
INNER JOIN `cms_section` AS `section`
    ON `section`.`cms_page_id` = `page`.`id`
    AND `section`.`cms_page_version_id` = `page`.`version_id`
    AND `section`.`version_id` = `page`.`version_id`
INNER JOIN `cms_block` AS `block`
    ON `block`.`cms_section_id` = `section`.`id`
    AND `block`.`cms_section_version_id` = `section`.`version_id`
    AND `block`.`version_id` = `section`.`version_id`
INNER JOIN `cms_slot` AS `slot`
    ON `slot`.`cms_block_id` = `block`.`id`
    AND `slot`.`cms_block_version_id` = `block`.`version_id`
    AND `slot`.`version_id` = `block`.`version_id`
INNER JOIN `cms_slot_translation` AS `slot_translation`
    ON `slot_translation`.`cms_slot_id` = `slot`.`id`
    AND `slot_translation`.`cms_slot_version_id` = `slot`.`version_id`
WHERE `page`.`version_id` = :versionId
    AND `block`.`name` = :cmsBlockName
    AND JSON_UNQUOTE(JSON_EXTRACT(`slot_translation`.`config`, '$.type.value')) = :slotType
LIMIT 1
SQL,
            [
                'cmsBlockName' => Migration1768545320RevocationRequestCmsForm::CMS_BLOCK_NAME,
                'slotType' => Migration1768545320RevocationRequestCmsForm::CMS_SLOT_TYPE,
                'versionId' => $versionByteId,
            ],
        );

        if (!\is_string($cmsPageByteId)) {
            return null;
        }

        return $cmsPageByteId;
    }

    private function repairRevocationRequestCmsPageConfiguration(Connection $connection, string $liveRevocationPageByteId): void
    {
        $configuration = $this->getGlobalRevocationPageConfiguration($connection);
        if ($configuration === null) {
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
            ['id' => $configurationId],
        );

        $this->disableGlobalRevocationButtonIfMissing($connection);
    }

    /**
     * @return array{id: string, configuration_value: mixed}|null
     */
    private function getGlobalRevocationPageConfiguration(Connection $connection): ?array
    {
        $configuration = $connection->fetchAssociative(
            'SELECT `id`, `configuration_value` FROM `system_config` WHERE `configuration_key` = :configKey AND `sales_channel_id` IS NULL LIMIT 1',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY],
        );

        if (!\is_array($configuration)) {
            return null;
        }

        $configurationId = $configuration['id'] ?? null;
        if (!\is_string($configurationId)) {
            return null;
        }

        return [
            'id' => $configurationId,
            'configuration_value' => $configuration['configuration_value'] ?? null,
        ];
    }

    private function hasAnyRevocationPageConfiguration(Connection $connection): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :configKey LIMIT 1',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY],
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
        if ((bool) $connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :configKey AND `sales_channel_id` IS NULL LIMIT 1',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_BUTTON_CONFIG_KEY],
        )) {
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

    private function createPageConfigurationValue(string $pageByteId): string
    {
        return json_encode(['_value' => Uuid::fromBytesToHex($pageByteId)], \JSON_THROW_ON_ERROR);
    }

    private function createCmsSlotConfig(): string
    {
        return json_encode([
            'type' => ['source' => 'static', 'value' => Migration1768545320RevocationRequestCmsForm::CMS_SLOT_TYPE],
            'mailReceiver' => ['source' => 'static', 'value' => []],
            'confirmationText' => ['source' => 'static', 'value' => ''],
        ], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function getLanguageIdsByLocalePrefix(Connection $connection, string $localePrefix, bool $invert = false): array
    {
        $operator = $invert ? 'NOT LIKE' : 'LIKE';

        $languageIds = $connection->fetchFirstColumn(
            \sprintf(
                <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE LOWER(`locale`.`code`) %s :localePrefix
ORDER BY `language`.`created_at` ASC, `language`.`id` ASC
SQL,
                $operator,
            ),
            ['localePrefix' => $localePrefix . '-%'],
        );

        $languageByteIds = [];
        foreach ($languageIds as $languageId) {
            if (!\is_string($languageId)) {
                continue;
            }

            $languageByteIds[] = $languageId;
        }

        return $languageByteIds;
    }
}
