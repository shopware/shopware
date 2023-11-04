<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1616076922AppPaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1616076922;
    }

    public function update(Connection $connection): void
    {
        $this->addAppPaymentMethod($connection);
        $this->addDefaultMediaFolder($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addAppPaymentMethod(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_payment_method` (
                `id`                        BINARY(16)          NOT NULL,
                `app_id`                    BINARY(16)          NULL,
                `payment_method_id`         BINARY(16)          NOT NULL,
                `app_name`                  VARCHAR(255)        NOT NULL,
                `identifier`                VARCHAR(255)        NOT NULL,
                `pay_url`                   VARCHAR(255)        NULL,
                `finalize_url`              VARCHAR(255)        NULL,
                `original_media_id`         BINARY(16)          NULL,
                `created_at`                DATETIME(3)         NOT NULL,
                `updated_at`                DATETIME(3)         NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `uniq.app_payment_method.payment_method_id`
                    UNIQUE (`payment_method_id`),
                CONSTRAINT `fk.app_payment_method.app_id`
                    FOREIGN KEY (`app_id`)
                    REFERENCES `app` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `fk.app_payment_method.payment_method_id`
                    FOREIGN KEY (`payment_method_id`)
                    REFERENCES `payment_method` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `fk.app_payment_method.original_media_id`
                    FOREIGN KEY (`original_media_id`)
                    REFERENCES `media` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function addDefaultMediaFolder(Connection $connection): void
    {
        $defaultFolderId = Uuid::randomBytes();
        $configurationId = Uuid::randomBytes();

        $connection->executeQuery(
            'REPLACE INTO `media_default_folder` SET
                id = :id,
                entity = :entity,
                association_fields = :association_fields,
                created_at = :created_at;',
            [
                'id' => $defaultFolderId,
                'entity' => PaymentMethodDefinition::ENTITY_NAME,
                'association_fields' => '["paymentMethods"]',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert('media_folder_configuration', [
            'id' => $configurationId,
            'thumbnail_quality' => 80,
            'create_thumbnails' => 1,
            'private' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('media_folder', [
            'id' => Uuid::randomBytes(),
            'default_folder_id' => $defaultFolderId,
            'name' => 'Payment Method Media',
            'media_folder_configuration_id' => $configurationId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
