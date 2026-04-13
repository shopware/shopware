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
        $enLanguageByteId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLanguageByteId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $cmsPageByteId = $this->createCmsPage($connection, $enLanguageByteId, $deLanguageByteId);
        $cmsSectionByteId = $this->createCmsSection($connection, $cmsPageByteId);
        $cmsBlockByteId = $this->createCmsBlock($connection, $cmsSectionByteId);
        $this->createCmsSlot($connection, $cmsBlockByteId, $enLanguageByteId, $deLanguageByteId);
    }

    private function createCmsPage(Connection $connection, ?string $enLanguageByteId, ?string $deLanguageByteId): string
    {
        $cmsPageByteId = $this->getCmsPageId($connection);
        if ($cmsPageByteId !== null) {
            return $cmsPageByteId;
        }

        $cmsPageByteId = Uuid::randomBytes();

        $connection->insert(
            'cms_page',
            [
                'id' => $cmsPageByteId,
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
                    'language_id' => $enLanguageByteId,
                    'name' => self::CMS_PAGE_TRANSLATIONS['en_name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($deLanguageByteId !== null) {
            $connection->insert(
                'cms_page_translation',
                [
                    'cms_page_id' => $cmsPageByteId,
                    'language_id' => $deLanguageByteId,
                    'name' => self::CMS_PAGE_TRANSLATIONS['de_name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        return $cmsPageByteId;
    }

    private function createCmsSection(Connection $connection, string $cmsPageByteId): string
    {
        $cmsSectionByteId = $this->getCmsSectionId($connection, $cmsPageByteId);
        if ($cmsSectionByteId !== null) {
            return $cmsSectionByteId;
        }
        $cmsSectionByteId = Uuid::randomBytes();

        $connection->insert(
            'cms_section',
            [
                'id' => $cmsSectionByteId,
                'cms_page_id' => $cmsPageByteId,
                'position' => 0,
                'type' => 'default',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        return $cmsSectionByteId;
    }

    private function createCmsBlock(Connection $connection, string $cmsSectionByteId): string
    {
        $cmsBlockByteId = $this->getCmsBlockId($connection);
        if ($cmsBlockByteId !== null) {
            return $cmsBlockByteId;
        }
        $cmsBlockByteId = Uuid::randomBytes();

        $connection->insert(
            'cms_block',
            [
                'id' => $cmsBlockByteId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $cmsSectionByteId,
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

    private function createCmsSlot(
        Connection $connection,
        string $cmsBlockByteId,
        ?string $enLanguageByteId,
        ?string $deLanguageByteId
    ): void {
        $versionByteId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $cmsSlotByteId = $this->getCmsSlotId($connection, $cmsBlockByteId);
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
                        'type' => ['source' => 'static', 'value' => self::CMS_SLOT_TYPE],
                        'mailReceiver' => ['source' => 'static', 'value' => []],
                        'confirmationText' => ['source' => 'static', 'value' => ''],
                    ], \JSON_THROW_ON_ERROR),
                ]
            );
        }

        if ($deLanguageByteId !== null) {
            $connection->insert(
                'cms_slot_translation',
                [
                    'cms_slot_id' => $cmsSlotByteId,
                    'cms_slot_version_id' => $versionByteId,
                    'language_id' => $deLanguageByteId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'config' => json_encode([
                        'type' => ['source' => 'static', 'value' => self::CMS_SLOT_TYPE],
                        'mailReceiver' => ['source' => 'static', 'value' => []],
                        'confirmationText' => ['source' => 'static', 'value' => ''],
                    ], \JSON_THROW_ON_ERROR),
                ]
            );
        }
    }

    private function getCmsPageId(Connection $connection): ?string
    {
        $sql = <<<'SQL'
SELECT `id` 
FROM `cms_page` AS `page`
INNER JOIN `cms_page_translation` AS `page_translation` ON `page`.`id` = `page_translation`.`cms_page_id`
WHERE page_translation.name = :name
SQL;

        $cmsPageByteId = $connection->executeQuery(
            $sql,
            ['name' => self::CMS_PAGE_TRANSLATIONS['en_name']]
        )->fetchOne();

        if (!\is_string($cmsPageByteId)) {
            return null;
        }

        return $cmsPageByteId;
    }

    private function getCmsSectionId(Connection $connection, string $cmsPageByteId): ?string
    {
        $cmsSectionByteId = $connection->executeQuery(
            'SELECT `id` FROM `cms_section` WHERE `cms_page_id` = :cmsPageId',
            ['cmsPageId' => $cmsPageByteId]
        )->fetchOne();

        if (!\is_string($cmsSectionByteId)) {
            return null;
        }

        return $cmsSectionByteId;
    }

    private function getCmsBlockId(Connection $connection): ?string
    {
        $cmsBlockByteId = $connection->executeQuery(
            'SELECT `id` FROM `cms_block` WHERE `name` = :cmsBlockName',
            ['cmsBlockName' => self::CMS_BLOCK_NAME]
        )->fetchOne();

        if (!\is_string($cmsBlockByteId)) {
            return null;
        }

        return $cmsBlockByteId;
    }

    private function getCmsSlotId(Connection $connection, string $cmsBlockByteId): ?string
    {
        $cmsSlotByteId = $connection->executeQuery(
            'SELECT `id` FROM `cms_slot` WHERE `cms_block_id` = :cmsBlockId',
            [
                'cmsBlockId' => $cmsBlockByteId,
            ]
        )->fetchOne();

        if (!\is_string($cmsSlotByteId)) {
            return null;
        }

        return $cmsSlotByteId;
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
}
