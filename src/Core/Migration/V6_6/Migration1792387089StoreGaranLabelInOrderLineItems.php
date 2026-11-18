<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Stores the current GARAN label of their product in the payload of order line items placed before line items stored
 * it, so they keep showing it once the storefront stops looking up their product. The values and conditions are the
 * ones of `GaranLabelResolver::resolveSnapshot()`, in the language of the order.
 *
 * @internal
 */
#[Package('inventory')]
class Migration1792387089StoreGaranLabelInOrderLineItems extends MigrationStep
{
    private const BATCH_SIZE = 1000;

    public function getCreationTimestamp(): int
    {
        return 1792387089;
    }

    public function update(Connection $connection): void
    {
        // variants only inherit a confirmed guarantee, so without one no product has a label
        if ($connection->fetchOne('SELECT 1 FROM `product` WHERE `guarantee_confirmed` = 1 LIMIT 1') === false) {
            return;
        }

        $garanSince = $connection->fetchOne(
            'SELECT `update` FROM `migration` WHERE `class` = :class',
            ['class' => Migration1783944800AddGaranLabel::class]
        );

        $lastId = '';

        do {
            $ids = $connection->fetchFirstColumn(
                'SELECT `id` FROM `order_line_item`
                WHERE `id` > :lastId AND `type` = :type AND `created_at` >= :garanSince
                ORDER BY `id` LIMIT :limit',
                ['lastId' => $lastId, 'type' => 'product', 'garanSince' => $garanSince, 'limit' => self::BATCH_SIZE],
                ['lastId' => ParameterType::BINARY, 'limit' => ParameterType::INTEGER]
            );

            if ($ids === []) {
                return;
            }

            $connection->executeStatement(
                'UPDATE `order_line_item` line_item
                    INNER JOIN `order` ON `order`.`id` = line_item.`order_id` AND `order`.`version_id` = line_item.`order_version_id`
                    LEFT JOIN `language` ON `language`.`id` = `order`.`language_id`
                    INNER JOIN `product` ON `product`.`id` = line_item.`product_id` AND `product`.`version_id` = :liveVersionId
                    LEFT JOIN `product` parent ON parent.`id` = `product`.`parent_id` AND parent.`version_id` = :liveVersionId
                    LEFT JOIN `product_manufacturer_translation` translation
                        ON translation.`product_manufacturer_id` = IFNULL(`product`.`product_manufacturer_id`, parent.`product_manufacturer_id`)
                        AND translation.`product_manufacturer_version_id` = :liveVersionId
                        AND translation.`language_id` = `order`.`language_id`
                    LEFT JOIN `product_manufacturer_translation` parent_language_translation
                        ON parent_language_translation.`product_manufacturer_id` = IFNULL(`product`.`product_manufacturer_id`, parent.`product_manufacturer_id`)
                        AND parent_language_translation.`product_manufacturer_version_id` = :liveVersionId
                        AND parent_language_translation.`language_id` = `language`.`parent_id`
                    LEFT JOIN `product_manufacturer_translation` system_language_translation
                        ON system_language_translation.`product_manufacturer_id` = IFNULL(`product`.`product_manufacturer_id`, parent.`product_manufacturer_id`)
                        AND system_language_translation.`product_manufacturer_version_id` = :liveVersionId
                        AND system_language_translation.`language_id` = :systemLanguageId
                SET line_item.`payload` = JSON_SET(IFNULL(line_item.`payload`, JSON_OBJECT()), \'$.garanLabel\', JSON_OBJECT(
                    \'guaranteeMonths\', IFNULL(`product`.`guarantee_months`, parent.`guarantee_months`),
                    \'brand\', TRIM(COALESCE(translation.`name`, parent_language_translation.`name`, system_language_translation.`name`)),
                    \'modelIdentifier\', TRIM(IFNULL(`product`.`manufacturer_number`, parent.`manufacturer_number`))
                ))
                WHERE line_item.`id` IN (:ids)
                    AND (line_item.`payload` IS NULL OR NOT JSON_CONTAINS_PATH(line_item.`payload`, \'one\', \'$.garanLabel\'))
                    AND IFNULL(`product`.`guarantee_confirmed`, parent.`guarantee_confirmed`) = 1
                    AND IFNULL(`product`.`guarantee_months`, parent.`guarantee_months`) > 24
                    AND IFNULL(`product`.`guarantee_months`, parent.`guarantee_months`) % 6 = 0
                    AND TRIM(COALESCE(translation.`name`, parent_language_translation.`name`, system_language_translation.`name`)) <> \'\'
                    AND TRIM(IFNULL(`product`.`manufacturer_number`, parent.`manufacturer_number`)) <> \'\'',
                [
                    'ids' => $ids,
                    'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'systemLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                ],
                ['ids' => ArrayParameterType::BINARY]
            );

            $lastId = end($ids);
        } while (\count($ids) === self::BATCH_SIZE);
    }
}
