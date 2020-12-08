<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1585816139FixMediaMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1585816139;
    }

    public function update(Connection $connection): void
    {
        $categoryProfileId = $connection->executeQuery(
            'SELECT `id` FROM `import_export_profile` WHERE `name` = :name AND `system_default` = 1 AND source_entity = "category"',
            ['name' => 'Default category']
        )->fetchColumn();

        if ($categoryProfileId) {
            $mapping = $this->getCategoryMapping();
            $connection->update('import_export_profile', ['mapping' => json_encode($mapping)], ['id' => $categoryProfileId]);
        }

        $mediaProfileId = $connection->executeQuery(
            'SELECT `id` FROM `import_export_profile` WHERE `name` = :name AND `system_default` = 1 AND source_entity = "media"',
            ['name' => 'Default media']
        )->fetchColumn();

        if ($mediaProfileId) {
            $mapping = $this->getMediaMapping();
            $connection->update('import_export_profile', ['mapping' => json_encode($mapping)], ['id' => $mediaProfileId]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getCategoryMapping(): array
    {
        return [
            ['key' => 'id', 'mappedKey' => 'id'],
            ['key' => 'parentId', 'mappedKey' => 'parent_id'],
            ['key' => 'active', 'mappedKey' => 'active'],

            ['key' => 'type', 'mappedKey' => 'type'],
            ['key' => 'visible', 'mappedKey' => 'visible'],
            ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
            ['key' => 'translations.DEFAULT.externalLink', 'mappedKey' => 'external_link'],
            ['key' => 'translations.DEFAULT.description', 'mappedKey' => 'description'],
            ['key' => 'translations.DEFAULT.metaTitle', 'mappedKey' => 'meta_title'],
            ['key' => 'translations.DEFAULT.metaDescription', 'mappedKey' => 'meta_description'],

            ['key' => 'media.id', 'mappedKey' => 'media_id'],
            ['key' => 'media.url', 'mappedKey' => 'media_url'],
            ['key' => 'media.mediaFolderId', 'mappedKey' => 'media_folder_id'],
            ['key' => 'media.mediaType', 'mappedKey' => 'media_type'],
            ['key' => 'media.translations.DEFAULT.title', 'mappedKey' => 'media_title'],
            ['key' => 'media.translations.DEFAULT.alt', 'mappedKey' => 'media_alt'],

            ['key' => 'cmsPageId', 'mappedKey' => 'cms_page_id'],
        ];
    }

    private function getMediaMapping(): array
    {
        return [
            ['key' => 'id', 'mappedKey' => 'id'],
            ['key' => 'mediaFolderId', 'mappedKey' => 'folder_id'],
            ['key' => 'url', 'mappedKey' => 'url'],

            ['key' => 'private', 'mappedKey' => 'private'],

            ['key' => 'mediaType', 'mappedKey' => 'type'],
            ['key' => 'translations.DEFAULT.title', 'mappedKey' => 'title'],
            ['key' => 'translations.DEFAULT.alt', 'mappedKey' => 'alt'],
        ];
    }
}
