<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1617877887AddCustomFieldToPromotionTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617877887;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'promotion_translation', 'custom_fields')) {
            $connection->executeStatement(
                'ALTER TABLE `promotion_translation`
                ADD COLUMN `custom_fields` JSON NULL AFTER `language_id`,
                ADD CONSTRAINT `json.promotion_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`));'
            );
        }
    }
}
