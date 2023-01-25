<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1570459127AddCmsSidebarLayout extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570459127;
    }

    public function update(Connection $connection): void
    {
        $this->addFilterPanelToDefault($connection);
        $this->addDefaultLayoutWithSidebar($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addFilterPanelToDefault(Connection $connection): void
    {
        $cmsPageId = $this->findDefaultLayoutId($connection);
        if ($cmsPageId === null) {
            return;
        }

        $sectionId = $connection->fetchOne(
            '
            SELECT id
            FROM cms_section
            WHERE cms_page_id = :cms_page_id',
            ['cms_page_id' => $cmsPageId]
        );
        $connection->executeStatement(
            '
            UPDATE cms_block
            SET position = position + 1
            WHERE cms_section_id = :cms_section_id
            AND position > 0',
            ['cms_section_id' => $sectionId]
        );

        $filterBlock = [
            'id' => Uuid::randomBytes(),
            'cms_section_id' => $sectionId,
            'position' => 1,
            'locked' => 1,
            'type' => 'sidebar-filter',
            'name' => 'Filter',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
        $connection->insert('cms_block', $filterBlock);

        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        // cms slots
        $filterSlot = [
            'id' => Uuid::randomBytes(),
            'locked' => 1,
            'cms_block_id' => $filterBlock['id'],
            'type' => 'sidebar-filter',
            'slot' => 'content',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'version_id' => $versionId,
        ];

        $slotTranslationData = [
            'cms_slot_id' => $filterSlot['id'],
            'cms_slot_version_id' => $versionId,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'config' => null,
        ];

        $connection->insert('cms_slot', $filterSlot);
        $connection->insert('cms_slot_translation', $slotTranslationData);
    }

    private function findDefaultLayoutId(Connection $connection): ?string
    {
        $result = $connection->fetchOne(
            '
            SELECT cms_page_id
            FROM cms_page_translation
            INNER JOIN cms_page ON cms_page.id = cms_page_translation.cms_page_id
            WHERE cms_page.locked
            AND name = :name',
            ['name' => 'Default category layout']
        );

        return $result === false ? null : (string) $result;
    }

    private function addDefaultLayoutWithSidebar(Connection $connection): void
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = $this->getDeDeId($connection);
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        // cms page
        $page = [
            'id' => Uuid::randomBytes(),
            'type' => 'product_list',
            'locked' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
        $pageEng = [
            'cms_page_id' => $page['id'],
            'language_id' => $languageEn,
            'name' => 'Default category layout with sidebar',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
        $pageDeu = [
            'cms_page_id' => $page['id'],
            'language_id' => $languageDe,
            'name' => 'Standard Kategorie-Layout mit Sidebar',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert('cms_page', $page);
        $connection->insert('cms_page_translation', $pageEng);
        if ($languageDe) {
            $connection->insert('cms_page_translation', $pageDeu);
        }

        $topSection = [
            'id' => Uuid::randomBytes(),
            'cms_page_id' => $page['id'],
            'position' => 0,
            'type' => 'default',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
        $sidebarSection = [
            'id' => Uuid::randomBytes(),
            'cms_page_id' => $page['id'],
            'position' => 1,
            'type' => 'sidebar',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert('cms_section', $topSection);
        $connection->insert('cms_section', $sidebarSection);

        // cms blocks
        $blocks = [
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $topSection['id'],
                'locked' => 1,
                'position' => 0,
                'type' => 'image-text',
                'name' => 'Category info',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $sidebarSection['id'],
                'section_position' => 'sidebar',
                'locked' => 1,
                'position' => 1,
                'type' => 'category-navigation',
                'name' => 'Sidebar navigation',
                'margin_bottom' => '30px',
                'background_media_mode' => 'cover',
            ],
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $sidebarSection['id'],
                'section_position' => 'sidebar',
                'locked' => 1,
                'position' => 2,
                'type' => 'sidebar-filter',
                'name' => 'Sidebar filter',
                'background_media_mode' => 'cover',
            ],
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_section_id' => $sidebarSection['id'],
                'section_position' => 'main',
                'locked' => 1,
                'position' => 2,
                'type' => 'product-listing',
                'name' => 'Category listing',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
        ];

        foreach ($blocks as $block) {
            $connection->insert('cms_block', $block);
        }

        // cms slots
        $slots = [
            ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $blocks[0]['id'], 'type' => 'image', 'slot' => 'left', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId],
            ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $blocks[0]['id'], 'type' => 'text', 'slot' => 'right', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId],

            ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $blocks[1]['id'], 'type' => 'category-navigation', 'slot' => 'content', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId],

            ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $blocks[2]['id'], 'type' => 'sidebar-filter', 'slot' => 'content', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId],

            ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $blocks[2]['id'], 'type' => 'sidebar-filter', 'slot' => 'content', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId],
            ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $blocks[3]['id'], 'type' => 'product-listing', 'slot' => 'content', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId],
        ];

        $slotTranslationData = [
            [
                'cms_slot_id' => $slots[0]['id'],
                'cms_slot_version_id' => $versionId,
                'language_id' => $languageEn,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => json_encode([
                    'media' => ['source' => 'mapped', 'value' => 'category.media'],
                    'displayMode' => ['source' => 'static', 'value' => 'cover'],
                    'url' => ['source' => 'static', 'value' => null],
                    'newTab' => ['source' => 'static', 'value' => false],
                    'minHeight' => ['source' => 'static', 'value' => '320px'],
                ]),
            ],
            [
                'cms_slot_id' => $slots[1]['id'],
                'cms_slot_version_id' => $versionId,
                'language_id' => $languageEn,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => json_encode([
                    'content' => ['source' => 'mapped', 'value' => 'category.description'],
                ]),
            ],
            [
                'cms_slot_id' => $slots[2]['id'],
                'cms_slot_version_id' => $versionId,
                'language_id' => $languageEn,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => null,
            ],
            [
                'cms_slot_id' => $slots[3]['id'],
                'cms_slot_version_id' => $versionId,
                'language_id' => $languageEn,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => json_encode([
                    'boxLayout' => ['source' => 'static', 'value' => 'standard'],
                ]),
            ],
        ];

        $slotTranslations = [];
        foreach ($slotTranslationData as $slotTranslationDatum) {
            $slotTranslationDatum['language_id'] = $languageEn;
            $slotTranslations[] = $slotTranslationDatum;

            if ($languageDe) {
                $slotTranslationDatum['language_id'] = $languageDe;
                $slotTranslations[] = $slotTranslationDatum;
            }
        }

        foreach ($slots as $slot) {
            $connection->insert('cms_slot', $slot);
        }

        foreach ($slotTranslations as $translation) {
            $connection->insert('cms_slot_translation', $translation);
        }
    }

    private function getDeDeId(Connection $connection): ?string
    {
        $result = $connection->fetchOne(
            '
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = "de-DE"'
        );

        if ($result === false || Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM) === $result) {
            return null;
        }

        return (string) $result;
    }
}
