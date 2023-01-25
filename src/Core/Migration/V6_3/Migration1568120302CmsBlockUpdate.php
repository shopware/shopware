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
class Migration1568120302CmsBlockUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1568120302;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `cms_block`
            ADD `cms_section_id` BINARY(16) NULL AFTER `id`,
            ADD `section_position` VARCHAR(50) DEFAULT "main" AFTER `position`
        ');

        $pages = $connection->fetchAllAssociative('SELECT * FROM `cms_page`');

        foreach ($pages as $page) {
            $section = [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_page_id' => $page['id'],
                'locked' => 0,
                'position' => 0,
                'type' => 'default',
                'sizing_mode' => 'boxed',
                'mobile_behavior' => 'wrap',
            ];

            $connection->insert('cms_section', $section);

            $connection->executeStatement(
                'UPDATE `cms_block` SET cms_section_id = :sectionId WHERE `cms_page_id` = :pageId',
                ['sectionId' => $section['id'], 'pageId' => $page['id']]
            );
        }

        $connection->executeStatement('
            ALTER TABLE `cms_block`
            MODIFY COLUMN `cms_page_id` BINARY(16) NULL;
        ');

        $connection->executeStatement('ALTER TABLE `cms_block` DROP FOREIGN KEY `fk.cms_block.cms_page_id`');
        $connection->executeStatement('ALTER TABLE `cms_block` ADD CONSTRAINT `fk.cms_block.cms_section_id` FOREIGN KEY (`cms_section_id`) REFERENCES `cms_section` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `cms_block` DROP COLUMN `cms_page_id`, DROP COLUMN `sizing_mode`');
    }
}
