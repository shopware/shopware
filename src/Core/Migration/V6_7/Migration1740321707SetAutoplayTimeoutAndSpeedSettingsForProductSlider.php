<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForProductSlider extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1740321707;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->beginTransaction();

            $this->setAutoplayTimeout($connection);
            $this->setSpeed($connection);

            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    public function setAutoplayTimeout(Connection $connection): void
    {
        $connection->executeQuery(
            <<<'SQL'
                UPDATE `cms_slot_translation`
                LEFT JOIN `cms_slot` ON `cms_slot`.`id` = `cms_slot_translation`.`cms_slot_id`
                SET `config` = JSON_SET(
                    `config`,
                    '$.autoplayTimeout',
                    JSON_OBJECT('value', 5000, 'source', 'static')
                )
                WHERE `cms_slot`.`type` = 'product-slider'
                    AND JSON_CONTAINS_PATH(`config`, 'ONE', '$.autoplayTimeout') != true
            SQL
        );
    }

    public function setSpeed(Connection $connection): void
    {
        $connection->executeQuery(
            <<<'SQL'
                UPDATE `cms_slot_translation`
                LEFT JOIN `cms_slot` ON `cms_slot`.`id` = `cms_slot_translation`.`cms_slot_id`
                SET `config` = JSON_SET(
                    `config`,
                    '$.speed',
                    JSON_OBJECT('value', 300, 'source', 'static')
                )
                WHERE `cms_slot`.`type` = 'product-slider'
                    AND JSON_CONTAINS_PATH(`config`, 'ONE', '$.speed') != true
            SQL
        );
    }
}
