<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1543386134TranslationCode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543386134;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `language`
            ADD COLUMN `translation_code_id` binary(16) NULL AFTER `locale_id`,
            ADD CONSTRAINT `uniq.translation_code_id` UNIQUE (translation_code_id),                        
            ADD KEY `fk.language.translation_code_id` (`translation_code_id`),
            ADD CONSTRAINT `fk.language.translation_code_id` FOREIGN KEY (`translation_code_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');

        $connection->executeQuery('
            UPDATE `language`
            SET translation_code_id = locale_id
            WHERE translation_code_id IS NULL'
        );

        // you cannot drop the unique index withou dropping the foreign key
        $connection->executeQuery('
            ALTER TABLE `language`
            DROP FOREIGN KEY `fk.language.locale_id`,
            DROP INDEX `uniq.locale_id`
        ');

        $connection->executeUpdate('
            UPDATE `language`
            SET locale_id = :localeId
            WHERE locale_id IS NULL',
            ['localeId' => Uuid::fromStringToBytes(Defaults::LOCALE_EN_GB)]
        );

        // add temporary dropped foreign key again
        $connection->executeQuery('
            ALTER TABLE `language`
            MODIFY COLUMN `locale_id` binary(16) NOT NULL,
            ADD CONSTRAINT `fk.language.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
