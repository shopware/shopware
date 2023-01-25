<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1585744384ChangeCategoryProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1585744384;
    }

    public function update(Connection $connection): void
    {
        $id = $connection->executeQuery(
            'SELECT `id` FROM `import_export_profile` WHERE `name` = :name AND `system_default` = 1',
            ['name' => 'Default category']
        )->fetchOne();

        if ($id) {
            $mapping = $this->getMapping();
            $connection->update('import_export_profile', ['mapping' => json_encode($mapping, \JSON_THROW_ON_ERROR)], ['id' => $id]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @return list<array{key: string, mappedKey: string}>
     */
    private function getMapping(): array
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
            ['key' => 'media.translations.DEFAULT.title', 'mappedKey' => 'media_alt'],
            ['key' => 'media.translations.DEFAULT.alt', 'mappedKey' => 'media_title'],

            ['key' => 'cmsPageId', 'mappedKey' => 'cms_page_id'],
        ];
    }
}
