<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1645019769UpdateCmsPageTranslation extends MigrationStep
{
    private Connection $connection;

    public function getCreationTimestamp(): int
    {
        return 1645019769;
    }

    public function update(Connection $connection): void
    {
        $this->connection = $connection;

        $cmsPageTranslations = $this->fetchCmsPageTranslationByName('Default category layout');
        foreach ($cmsPageTranslations as $cmsPageTranslation) {
            $connection->update(
                'cms_page_translation',
                ['name' => 'Default listing layout'],
                $cmsPageTranslation,
            );
        }

        $cmsPageTranslations = $this->fetchCmsPageTranslationByName('Default category layout with sidebar');
        foreach ($cmsPageTranslations as $cmsPageTranslation) {
            $connection->update(
                'cms_page_translation',
                ['name' => 'Default listing layout with sidebar'],
                $cmsPageTranslation,
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @return list<array<string, string>>
     */
    private function fetchCmsPageTranslationByName(string $cmsPageTranslationName): array
    {
        /** @var list<array<string, string>> $translationData */
        $translationData = $this->connection->fetchAllAssociative(
            'SELECT `cms_page_id`, `cms_page_version_id`, `language_id`
            FROM `cms_page_translation` INNER JOIN `cms_page` ON `cms_page_translation`.`cms_page_id` = `cms_page`.`id`
            WHERE `name` = :cmsPageTranslationName AND `locked` = 1 AND `cms_page_translation`.`updated_at` IS NULL',
            [
                'cmsPageTranslationName' => $cmsPageTranslationName,
            ]
        );

        return $translationData;
    }
}
