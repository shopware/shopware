<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1776384001AddCategoryTranslationLinkMediaId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776384001;
    }

    public function update(Connection $connection): void
    {
        $columnAdded = $this->addColumn($connection, 'category_translation', 'link_media_id', 'BINARY(16)');

        if ($columnAdded) {
            $connection->executeStatement(
                'ALTER TABLE `category_translation`'
                . ' ADD CONSTRAINT `fk.category_translation.link_media_id`'
                . ' FOREIGN KEY (`link_media_id`) REFERENCES `media` (`id`)'
                . ' ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
