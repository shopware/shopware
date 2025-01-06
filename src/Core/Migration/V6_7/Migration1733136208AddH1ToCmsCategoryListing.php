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
#[Package('core')]
class Migration1733136208AddH1ToCmsCategoryListing extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733136208;
    }

    public function update(Connection $connection): void
    {
        $defaultListingLayoutId = $this->findDefaultLayoutId($connection, 'Default listing layout');
        $defaultListingSidebarLayoutId = $this->findDefaultLayoutId($connection, 'Default listing layout with sidebar');

        if ($defaultListingLayoutId !== null) {
            $this->addH1ToDefaultListing($connection, $defaultListingLayoutId);
        }

        if ($defaultListingSidebarLayoutId !== null) {
            $this->addH1ToDefaultListing($connection, $defaultListingSidebarLayoutId);
        }
    }

    private function addH1ToDefaultListing(Connection $connection, string $cmsPageId): void
    {
        $sectionId = $connection->fetchOne(
            'SELECT id
            FROM cms_section
            WHERE cms_page_id = :cms_page_id',
            ['cms_page_id' => $cmsPageId]
        );
        $connection->executeStatement(
            'UPDATE cms_block
            SET position = position + 1
            WHERE cms_section_id = :cms_section_id
            AND position >= 0',
            ['cms_section_id' => $sectionId]
        );

        $categoryNameBlock = [
            'id' => Uuid::randomBytes(),
            'cms_section_id' => $sectionId,
            'position' => 0,
            'locked' => 1,
            'type' => 'text',
            'name' => 'Category name',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
        $connection->insert('cms_block', $categoryNameBlock);

        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $categoryNameSlot = [
            'id' => Uuid::randomBytes(),
            'locked' => 1,
            'cms_block_id' => $categoryNameBlock['id'],
            'type' => 'text',
            'slot' => 'content',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'version_id' => $versionId,
        ];

        $slotTranslationData = [
            'cms_slot_id' => $categoryNameSlot['id'],
            'cms_slot_version_id' => $versionId,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'config' => json_encode([
                'content' => [
                    'source' => 'static',
                    'value' => '<h1>{{ category.name }}</h1>',
                ],
            ], \JSON_THROW_ON_ERROR),
        ];

        $connection->insert('cms_slot', $categoryNameSlot);
        $connection->insert('cms_slot_translation', $slotTranslationData);
    }

    private function findDefaultLayoutId(Connection $connection, string $name): ?string
    {
        $result = $connection->fetchOne(
            'SELECT cms_page_id
            FROM cms_page_translation
            INNER JOIN cms_page ON cms_page.id = cms_page_translation.cms_page_id
            WHERE cms_page.locked
            AND name = :name',
            ['name' => $name]
        );

        return $result ?: null;
    }
}
