<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1732608755MigrateNavigationSettingsForProductSlider extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1732608755;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->beginTransaction();

            $this->setDeactivatedNavigationArrows($connection);
            $this->setActivatedNavigationArrows($connection);
            $this->removeOldSliderConfig($connection);

            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    public function setDeactivatedNavigationArrows(Connection $connection): void
    {
        $connection->executeQuery(
            <<<'SQL'
                UPDATE `cms_slot_translation`
                LEFT JOIN `cms_slot` ON `cms_slot`.`id` = `cms_slot_translation`.`cms_slot_id`
                SET `config` = JSON_SET(
                   `config`,
                    '$.navigationArrows',
                    JSON_OBJECT('value', 'none', 'source', 'static')
                )
                WHERE `cms_slot`.`type` = 'product-slider'
                    AND (
                        JSON_CONTAINS_PATH(`config`, 'ONE', '$.navigation') != true
                        OR JSON_EXTRACT(`config`, '$.navigation.value') != true
                    );
            SQL
        );
    }

    public function setActivatedNavigationArrows(Connection $connection): void
    {
        $connection->executeQuery(
            <<<'SQL'
                UPDATE `cms_slot_translation`
                LEFT JOIN `cms_slot` ON `cms_slot`.`id` = `cms_slot_translation`.`cms_slot_id`
                SET `config` = JSON_SET(
                   `config`,
                    '$.navigationArrows',
                    JSON_OBJECT('value', 'outside', 'source', 'static')
                )
                WHERE `cms_slot`.`type` = 'product-slider'
                    AND JSON_CONTAINS_PATH(`config`, 'ONE', '$.navigation') = true
                    AND JSON_EXTRACT(`config`, '$.navigation.value') = true;
            SQL
        );
    }

    public function removeOldSliderConfig(Connection $connection): void
    {
        $connection->executeQuery(
            <<<'SQL'
                UPDATE `cms_slot_translation`
                LEFT JOIN `cms_slot` ON `cms_slot`.`id` = `cms_slot_translation`.`cms_slot_id`
                SET `config` = JSON_REMOVE(`config`, '$.navigation')
                WHERE `cms_slot`.`type` = 'product-slider';
            SQL
        );
    }
}
