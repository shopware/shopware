<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1553249097DeliveryTimeToOwnTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553249097;
    }

    public function update(Connection $connection): void
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        $oneToThree = Uuid::randomBytes();
        $twoToFive = Uuid::randomBytes();
        $oneToTwoWeeks = Uuid::randomBytes();
        $threeToFourWeeks = Uuid::randomBytes();

        $connection->exec('
            CREATE TABLE delivery_time (
              `id` BINARY(16) NOT NULL,
              `min` INT(3) NOT NULL,
              `max` INT(3) NOT NULL,
              `unit` VARCHAR(255) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE delivery_time_translation (
              `delivery_time_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`delivery_time_id`, `language_id`),
              CONSTRAINT `fk.delivery_time_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.delivery_time_translation.delivery_time_id` FOREIGN KEY (`delivery_time_id`)
                REFERENCES `delivery_time` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            ALTER TABLE shipping_method
              ADD delivery_time_id BINARY(16),
              ADD CONSTRAINT `fk.shipping_method.delivery_time_id`
                FOREIGN KEY (`delivery_time_id`) REFERENCES `delivery_time` (`id`);
        ');

        $connection->insert('delivery_time', ['id' => $oneToThree, 'min' => 1, 'max' => 3, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $oneToThree, 'language_id' => $languageEn, 'name' => '1-3 days', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $oneToThree, 'language_id' => $languageDe, 'name' => '1-3 Tage', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time', ['id' => $twoToFive, 'min' => 2, 'max' => 5, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $twoToFive, 'language_id' => $languageEn, 'name' => '2-5 days', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $twoToFive, 'language_id' => $languageDe, 'name' => '2-5 Tage', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time', ['id' => $oneToTwoWeeks, 'min' => 1, 'max' => 2, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_WEEK, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $oneToTwoWeeks, 'language_id' => $languageEn, 'name' => '1-2 weeks', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $oneToTwoWeeks, 'language_id' => $languageDe, 'name' => '1-2 Wochen', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time', ['id' => $threeToFourWeeks, 'min' => 3, 'max' => 4, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_WEEK, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $threeToFourWeeks, 'language_id' => $languageEn, 'name' => '3-4 weeks', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $threeToFourWeeks, 'language_id' => $languageDe, 'name' => '3-4 Wochen', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->update('shipping_method', ['delivery_time_id' => $oneToThree], [1 => 1]);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE shipping_method 
              DROP COLUMN min_delivery_time,
              DROP COLUMN max_delivery_time;
        ');
    }
}
