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
#[Package('after-sales')]
class Migration1768545320RevocationRequestCmsForm extends MigrationStep
{
    public const CMS_PAGE_TRANSLATIONS = [
        'en_name' => 'Default shop page layout with revocation request form',
        'de_name' => 'Standard Shopseiten-Layout mit Formular für Widerrufsanträge',
    ];

    public const CMS_SLOT_TYPE = 'revocationRequest';

    public const CMS_BLOCK_NAME = 'Revocation request form';

    public function getCreationTimestamp(): int
    {
        return 1768545320;
    }

    public function update(Connection $connection): void
    {
        $deLanguageByteIds = $this->getLanguageIdsByLocalePrefix($connection, 'de');
        $enLanguageByteIds = $this->getLanguageIdsByLocalePrefix($connection, 'de', true);
        $versionByteId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $cmsPageByteId = $this->createCmsPage($connection, $versionByteId, $enLanguageByteIds, $deLanguageByteIds);
        $cmsSectionByteId = $this->createCmsSection($connection, $cmsPageByteId, $versionByteId);
        $cmsBlockByteId = $this->createCmsBlock($connection, $cmsSectionByteId, $versionByteId);
        $this->createCmsSlot($connection, $cmsBlockByteId, $versionByteId, $enLanguageByteIds, $deLanguageByteIds);
    }

    /**
     * @param list<string> $enLanguageByteIds
     * @param list<string> $deLanguageByteIds
     */
    private function createCmsPage(Connection $connection, string $versionByteId, array $enLanguageByteIds, array $deLanguageByteIds): string
    {
        $cmsPageByteId = $this->getCmsPageId($connection, $versionByteId);
        if ($cmsPageByteId !== null) {
            $this->createCmsPageTranslations($connection, $cmsPageByteId, $versionByteId, $enLanguageByteIds, $deLanguageByteIds);

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

        $this->createCmsPageTranslations($connection, $cmsPageByteId, $versionByteId, $enLanguageByteIds, $deLanguageByteIds);

        return $cmsPageByteId;
    }

    /**
     * @param list<string> $enLanguageByteIds
     * @param list<string> $deLanguageByteIds
     */
    private function createCmsPageTranslations(Connection $connection, string $cmsPageByteId, string $versionByteId, array $enLanguageByteIds, array $deLanguageByteIds): void
    {
        foreach ($enLanguageByteIds as $enLanguageByteId) {
            $this->createCmsPageTranslation($connection, $cmsPageByteId, $versionByteId, $enLanguageByteId, self::CMS_PAGE_TRANSLATIONS['en_name']);
        }

        foreach ($deLanguageByteIds as $deLanguageByteId) {
            $this->createCmsPageTranslation($connection, $cmsPageByteId, $versionByteId, $deLanguageByteId, self::CMS_PAGE_TRANSLATIONS['de_name']);
        }
    }

    private function createCmsPageTranslation(Connection $connection, string $cmsPageByteId, string $versionByteId, string $languageByteId, string $name): void
    {
        if ($this->hasCmsPageTranslation($connection, $cmsPageByteId, $versionByteId, $languageByteId)) {
            return;
        }

        $connection->insert(
            'cms_page_translation',
            [
                'cms_page_id' => $cmsPageByteId,
                'cms_page_version_id' => $versionByteId,
                'language_id' => $languageByteId,
                'name' => $name,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
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
                'name' => self::CMS_BLOCK_NAME,
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ]
        );

        return $cmsBlockByteId;
    }

    /**
     * @param list<string> $enLanguageByteIds
     * @param list<string> $deLanguageByteIds
     */
    private function createCmsSlot(
        Connection $connection,
        string $cmsBlockByteId,
        string $versionByteId,
        array $enLanguageByteIds,
        array $deLanguageByteIds
    ): void {
        $cmsSlotByteId = $this->getCmsSlotId($connection, $cmsBlockByteId, $versionByteId);
        if ($cmsSlotByteId !== null) {
            $this->createCmsSlotTranslations($connection, $cmsSlotByteId, $versionByteId, $enLanguageByteIds, $deLanguageByteIds);

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

        $this->createCmsSlotTranslations($connection, $cmsSlotByteId, $versionByteId, $enLanguageByteIds, $deLanguageByteIds);
    }

    /**
     * @param list<string> $enLanguageByteIds
     * @param list<string> $deLanguageByteIds
     */
    private function createCmsSlotTranslations(Connection $connection, string $cmsSlotByteId, string $versionByteId, array $enLanguageByteIds, array $deLanguageByteIds): void
    {
        foreach ($enLanguageByteIds as $enLanguageByteId) {
            $this->createCmsSlotTranslation($connection, $cmsSlotByteId, $versionByteId, $enLanguageByteId);
        }

        foreach ($deLanguageByteIds as $deLanguageByteId) {
            $this->createCmsSlotTranslation($connection, $cmsSlotByteId, $versionByteId, $deLanguageByteId);
        }
    }

    private function createCmsSlotTranslation(Connection $connection, string $cmsSlotByteId, string $versionByteId, string $languageByteId): void
    {
        if ($this->hasCmsSlotTranslation($connection, $cmsSlotByteId, $versionByteId, $languageByteId)) {
            return;
        }

        $connection->insert(
            'cms_slot_translation',
            [
                'cms_slot_id' => $cmsSlotByteId,
                'cms_slot_version_id' => $versionByteId,
                'language_id' => $languageByteId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => $this->createCmsSlotConfig(),
            ]
        );
    }

    private function createCmsSlotConfig(): string
    {
        return json_encode([
            'type' => ['source' => 'static', 'value' => self::CMS_SLOT_TYPE],
            'mailReceiver' => ['source' => 'static', 'value' => []],
            'confirmationText' => ['source' => 'static', 'value' => ''],
        ], \JSON_THROW_ON_ERROR);
    }

    private function getCmsPageId(Connection $connection, string $versionByteId): ?string
    {
        $sql = <<<'SQL'
SELECT `id` 
FROM `cms_page` AS `page`
INNER JOIN `cms_page_translation` AS `page_translation` ON `page`.`id` = `page_translation`.`cms_page_id`
    AND `page`.`version_id` = `page_translation`.`cms_page_version_id`
WHERE `page`.`version_id` = :versionId
    AND (`page_translation`.`name` = :enName OR `page_translation`.`name` = :deName)
SQL;

        $cmsPageByteId = $connection->executeQuery(
            $sql,
            [
                'deName' => self::CMS_PAGE_TRANSLATIONS['de_name'],
                'enName' => self::CMS_PAGE_TRANSLATIONS['en_name'],
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
                'cmsBlockName' => self::CMS_BLOCK_NAME,
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

    private function hasCmsPageTranslation(Connection $connection, string $cmsPageByteId, string $versionByteId, string $languageByteId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `cms_page_translation` WHERE `cms_page_id` = :cmsPageId AND `cms_page_version_id` = :versionId AND `language_id` = :languageId',
            [
                'cmsPageId' => $cmsPageByteId,
                'languageId' => $languageByteId,
                'versionId' => $versionByteId,
            ]
        );
    }

    private function hasCmsSlotTranslation(Connection $connection, string $cmsSlotByteId, string $versionByteId, string $languageByteId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `cms_slot_translation` WHERE `cms_slot_id` = :cmsSlotId AND `cms_slot_version_id` = :versionId AND `language_id` = :languageId',
            [
                'cmsSlotId' => $cmsSlotByteId,
                'languageId' => $languageByteId,
                'versionId' => $versionByteId,
            ]
        );
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
                $operator
            ),
            ['localePrefix' => $localePrefix . '-%']
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
