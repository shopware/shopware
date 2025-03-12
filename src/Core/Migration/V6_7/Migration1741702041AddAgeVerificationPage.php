<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1741702041AddAgeVerificationPage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1741702041;
    }

    public function update(Connection $connection): void
    {
        $layoutId = Uuid::randomBytes();
        $sectionId = Uuid::randomBytes();
        $blockId = Uuid::randomBytes();
        $slotTextId = Uuid::randomBytes();
        $slotButtonId = Uuid::randomBytes();
        $languageId = Uuid::fromHexToBytes('2fbb5fe2e29a4d70aa5854ce7ce3e20b'); 
        $versionId = Uuid::fromHexToBytes('0fa91ce3e96a4bc2be4bd9ce752c3425'); 

        $connection->executeStatement('
            INSERT INTO cms_page (id, version_id, type, locked, created_at)
            VALUES (:id, :versionId, :type, :locked, NOW())
        ', [
            'id' => $layoutId,
            'versionId' => $versionId,
            'type' => 'landingpage', 
            'locked' => 0
        ]);

        $connection->executeStatement('
            INSERT INTO cms_page_translation (cms_page_id, cms_page_version_id, language_id, name, created_at)
            VALUES (:cmsPageId, :cmsPageVersionId, :languageId, :name, NOW())
        ', [
            'cmsPageId' => $layoutId,
            'cmsPageVersionId' => $versionId,
            'languageId' => $languageId,
            'name' => 'Altersverifikation'
        ]);

        $connection->executeStatement('
            INSERT INTO cms_section (id, version_id, cms_page_id, cms_page_version_id, position, type, sizing_mode, created_at)
            VALUES (:id, :versionId, :pageId, :pageVersionId, 0, :type, :sizingMode, NOW())
        ', [
            'id' => $sectionId,
            'versionId' => $versionId,
            'pageId' => $layoutId,
            'pageVersionId' => $versionId,
            'type' => 'default',
            'sizingMode' => 'boxed'
        ]);

        $connection->executeStatement('
            INSERT INTO cms_block (id, version_id, cms_section_id, cms_section_version_id, position, type, name, locked, created_at)
            VALUES (:id, :versionId, :sectionId, :sectionVersionId, 0, :type, :name, :locked, NOW())
        ', [
            'id' => $blockId,
            'versionId' => $versionId,
            'sectionId' => $sectionId,
            'sectionVersionId' => $versionId,
            'type' => 'text',
            'name' => 'Altersverifikation Block',
            'locked' => 0
        ]);

        $connection->executeStatement('
            INSERT INTO cms_slot (id, version_id, cms_block_id, cms_block_version_id, type, slot, locked, created_at)
            VALUES (:id, :versionId, :blockId, :blockVersionId, :type, :slot, :locked, NOW())
        ', [
            'id' => $slotTextId,
            'versionId' => $versionId,
            'blockId' => $blockId,
            'blockVersionId' => $versionId,
            'type' => 'text',
            'slot' => 'content',
            'locked' => 0
        ]);

        $connection->executeStatement('
            INSERT INTO cms_slot (id, version_id, cms_block_id, cms_block_version_id, type, slot, locked, created_at)
            VALUES (:id, :versionId, :blockId, :blockVersionId, :type, :slot, :locked, NOW())
        ', [
            'id' => $slotButtonId,
            'versionId' => $versionId,
            'blockId' => $blockId,
            'blockVersionId' => $versionId,
            'type' => 'button',
            'slot' => 'buttons',
            'locked' => 0
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {

    }
}
