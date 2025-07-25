<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1752219159AddLanguageActive extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752219159;
    }

    public function update(Connection $connection): void
    {
        $addedColumn = $this->addColumn(
            $connection,
            table: 'language',
            column: 'active',
            type: 'TINYINT(1)',
            nullable: false,
            default: '0',
        );

        if ($addedColumn) {
            $connection->executeStatement(
                <<<'SQL'
                    UPDATE `language`
                    SET `active` = 1
                    WHERE `active` != 1;
                SQL
            );

            if (!$this->isInstallation() && $connection->createSchemaManager()->tableExists('swag_language_pack_language')) {
                $connection->executeStatement(
                    <<<'SQL'
                        UPDATE `language`
                        RIGHT JOIN `swag_language_pack_language` pack_language
                            ON `language`.`id` = `pack_language`.`language_id`
                        SET `language`.`active` = `pack_language`.`sales_channel_active`
                        WHERE `pack_language`.`sales_channel_active` IS NOT NULL;
                    SQL
                );
            }
        }
    }
}
