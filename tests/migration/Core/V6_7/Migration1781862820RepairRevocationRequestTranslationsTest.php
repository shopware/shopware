<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1768545320RevocationRequestCmsForm;
use Shopware\Core\Migration\V6_7\Migration1768545322AssignRevocationPageToSystemConfigSetting;
use Shopware\Core\Migration\V6_7\Migration1781862820RepairRevocationRequestTranslations;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1781862820RepairRevocationRequestTranslations::class)]
class Migration1781862820RepairRevocationRequestTranslationsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1781862820, (new Migration1781862820RepairRevocationRequestTranslations())->getCreationTimestamp());
    }

    public function testUpdateRepairsRegionalAndForeignRevocationTranslations(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->deleteRevocationSystemConfig();
            $this->deleteRevocationCmsPages();
            $this->deleteRevocationMailTemplates();

            $deChLanguageByteId = $this->createLanguage('de-CH');
            $enUsLanguageByteId = $this->createLanguage('en-US');
            $frChLanguageByteId = $this->createLanguage('fr-CH', 'de-LI');

            $migration = new Migration1781862820RepairRevocationRequestTranslations();
            $migration->update($this->connection);
            $migration->update($this->connection);

            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT,
                $deChLanguageByteId,
                'Widerrufsantrag erhalten'
            );
            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT,
                $enUsLanguageByteId,
                'Revocation request received'
            );
            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT,
                $frChLanguageByteId,
                'Revocation request received'
            );

            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER,
                $deChLanguageByteId,
                'Widerrufsantrag gesendet'
            );
            $this->assertMailTemplateTranslation(
                MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER,
                $frChLanguageByteId,
                'Revocation request sent'
            );

            $cmsPageByteId = $this->getRevocationCmsPageId();
            static::assertIsString($cmsPageByteId);

            $deChPageTranslationName = $this->getCmsPageTranslationName($cmsPageByteId, $deChLanguageByteId);
            static::assertSame(Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['de_name'], $deChPageTranslationName);

            $frChPageTranslationName = $this->getCmsPageTranslationName($cmsPageByteId, $frChLanguageByteId);
            static::assertSame(Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'], $frChPageTranslationName);

            $cmsSlotByteId = $this->getRevocationCmsSlotId();
            static::assertIsString($cmsSlotByteId);
            static::assertSame(1, $this->countCmsSlotTranslations($cmsSlotByteId, $deChLanguageByteId));
            static::assertSame(1, $this->countCmsSlotTranslations($cmsSlotByteId, $frChLanguageByteId));

            $configuredPageId = $this->getGlobalRevocationPageConfigValue();
            static::assertSame(Uuid::fromBytesToHex($cmsPageByteId), $configuredPageId);
            static::assertFalse($this->getGlobalRevocationButtonConfigValue());
        } finally {
            $this->connection->rollBack();
        }
    }

    private function deleteRevocationSystemConfig(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` IN (:pageConfigKey, :buttonConfigKey)',
            [
                'buttonConfigKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_BUTTON_CONFIG_KEY,
                'pageConfigKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY,
            ]
        );
    }

    private function deleteRevocationCmsPages(): void
    {
        $pageRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT DISTINCT `page`.`id`, `page`.`version_id`
FROM `cms_page` AS `page`
INNER JOIN `cms_page_translation` AS `page_translation`
    ON `page_translation`.`cms_page_id` = `page`.`id`
    AND `page_translation`.`cms_page_version_id` = `page`.`version_id`
WHERE `page_translation`.`name` = :enName OR `page_translation`.`name` = :deName
SQL,
            [
                'deName' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['de_name'],
                'enName' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'],
            ]
        );

        foreach ($pageRows as $pageRow) {
            static::assertIsString($pageRow['id']);
            static::assertIsString($pageRow['version_id']);

            $this->connection->delete('cms_page', [
                'id' => $pageRow['id'],
                'version_id' => $pageRow['version_id'],
            ]);
        }
    }

    private function deleteRevocationMailTemplates(): void
    {
        foreach ([MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT, MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER] as $technicalName) {
            $mailTemplateTypeByteId = $this->connection->fetchOne(
                'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName LIMIT 1',
                ['technicalName' => $technicalName]
            );

            if (!\is_string($mailTemplateTypeByteId)) {
                continue;
            }

            $mailTemplateByteIds = $this->connection->fetchFirstColumn(
                'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId',
                ['mailTemplateTypeId' => $mailTemplateTypeByteId]
            );

            foreach ($mailTemplateByteIds as $mailTemplateByteId) {
                if (!\is_string($mailTemplateByteId)) {
                    continue;
                }

                $this->connection->delete('mail_template_translation', ['mail_template_id' => $mailTemplateByteId]);
                $this->connection->delete('mail_template', ['id' => $mailTemplateByteId]);
            }

            $this->connection->delete('mail_template_type_translation', ['mail_template_type_id' => $mailTemplateTypeByteId]);
            $this->connection->delete('mail_template_type', ['id' => $mailTemplateTypeByteId]);
        }
    }

    private function assertMailTemplateTranslation(string $technicalName, string $languageByteId, string $expectedSubject): void
    {
        $subject = $this->connection->fetchOne(
            <<<'SQL'
SELECT `mail_template_translation`.`subject`
FROM `mail_template_translation`
INNER JOIN `mail_template`
    ON `mail_template`.`id` = `mail_template_translation`.`mail_template_id`
INNER JOIN `mail_template_type`
    ON `mail_template_type`.`id` = `mail_template`.`mail_template_type_id`
WHERE `mail_template_type`.`technical_name` = :technicalName
    AND `mail_template`.`system_default` = 1
    AND `mail_template_translation`.`language_id` = :languageId
LIMIT 1
SQL,
            [
                'languageId' => $languageByteId,
                'technicalName' => $technicalName,
            ]
        );

        static::assertSame($expectedSubject, $subject);
    }

    private function getRevocationCmsPageId(): ?string
    {
        $cmsPageByteId = $this->connection->fetchOne(
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
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        if (!\is_string($cmsPageByteId)) {
            return null;
        }

        return $cmsPageByteId;
    }

    private function getRevocationCmsSlotId(): ?string
    {
        $cmsSlotByteId = $this->connection->fetchOne(
            <<<'SQL'
SELECT `slot`.`id`
FROM `cms_slot` AS `slot`
INNER JOIN `cms_slot_translation` AS `slot_translation`
    ON `slot_translation`.`cms_slot_id` = `slot`.`id`
    AND `slot_translation`.`cms_slot_version_id` = `slot`.`version_id`
WHERE JSON_UNQUOTE(JSON_EXTRACT(`slot_translation`.`config`, '$.type.value')) = :slotType
LIMIT 1
SQL,
            ['slotType' => Migration1768545320RevocationRequestCmsForm::CMS_SLOT_TYPE]
        );

        if (!\is_string($cmsSlotByteId)) {
            return null;
        }

        return $cmsSlotByteId;
    }

    private function getCmsPageTranslationName(string $cmsPageByteId, string $languageByteId): ?string
    {
        $name = $this->connection->fetchOne(
            'SELECT `name` FROM `cms_page_translation` WHERE `cms_page_id` = :cmsPageId AND `language_id` = :languageId LIMIT 1',
            [
                'cmsPageId' => $cmsPageByteId,
                'languageId' => $languageByteId,
            ]
        );

        if (!\is_string($name)) {
            return null;
        }

        return $name;
    }

    private function countCmsSlotTranslations(string $cmsSlotByteId, string $languageByteId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `cms_slot_translation` WHERE `cms_slot_id` = :cmsSlotId AND `language_id` = :languageId',
            [
                'cmsSlotId' => $cmsSlotByteId,
                'languageId' => $languageByteId,
            ]
        );
    }

    private function getGlobalRevocationPageConfigValue(): mixed
    {
        $configurationValue = $this->connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :configKey AND `sales_channel_id` IS NULL LIMIT 1',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY]
        );

        if (!\is_string($configurationValue)) {
            return null;
        }

        $decoded = json_decode($configurationValue, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded['_value'] ?? null;
    }

    private function getGlobalRevocationButtonConfigValue(): mixed
    {
        $configurationValue = $this->connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :configKey AND `sales_channel_id` IS NULL LIMIT 1',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_BUTTON_CONFIG_KEY]
        );

        if (!\is_string($configurationValue)) {
            return null;
        }

        $decoded = json_decode($configurationValue, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded['_value'] ?? null;
    }

    private function createLanguage(string $localeCode, ?string $translationCode = null): string
    {
        $localeByteId = $this->getOrCreateLocale($localeCode);
        $translationCodeByteId = $translationCode === null ? $localeByteId : $this->getOrCreateLocale($translationCode);
        $languageByteId = Uuid::randomBytes();

        $this->connection->insert('language', [
            'id' => $languageByteId,
            'name' => $localeCode,
            'locale_id' => $localeByteId,
            'translation_code_id' => $translationCodeByteId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $languageByteId;
    }

    private function getOrCreateLocale(string $localeCode): string
    {
        $localeByteId = $this->connection->fetchOne(
            'SELECT `id` FROM `locale` WHERE `code` = :code LIMIT 1',
            ['code' => $localeCode]
        );

        if (\is_string($localeByteId)) {
            return $localeByteId;
        }

        $localeByteId = Uuid::randomBytes();

        $this->connection->insert('locale', [
            'id' => $localeByteId,
            'code' => $localeCode,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $localeByteId;
    }
}
