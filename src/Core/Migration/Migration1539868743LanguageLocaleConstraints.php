<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1539868743LanguageLocaleConstraints extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1539868743;
    }

    public function update(Connection $connection): void
    {
        // drop all foreign keys

        $connection->executeQuery('
            ALTER TABLE `locale_translation`
            DROP FOREIGN KEY `locale_translation_ibfk_2`
        ');

        $connection->executeQuery('
            ALTER TABLE `user`
            DROP FOREIGN KEY `fk_user.locale_id`,
            DROP INDEX `fk_user.locale_id`
        ');

        $connection->executeQuery('
            ALTER TABLE `language`
            DROP INDEX `locale_id`,
            DROP INDEX `fk_language.locale_id`,
            DROP FOREIGN KEY `fk_language.locale_id`
        ');

        // change primary key and make locale_version columns optional

        $connection->executeQuery('
            ALTER TABLE `locale`
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (id, tenant_id),
            
            MODIFY `version_id` binary(16) NULL'
        );

        // add foreign keys and make locale_version columns optional

        $connection->executeQuery('
            ALTER TABLE `locale_translation`
            
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`locale_id`, `locale_tenant_id`, `language_id`, `language_tenant_id`),
            
            MODIFY `locale_version_id` binary(16) NULL,
            
            ADD CONSTRAINT `locale_translation_ibfk_2` FOREIGN KEY (`locale_id`, `locale_tenant_id`) REFERENCES `locale` (`id`, `tenant_id`)  ON DELETE CASCADE ON UPDATE CASCADE'
        );

        $connection->executeQuery('
            ALTER TABLE `user`
            
            MODIFY `locale_version_id` binary(16) NULL,
            
            ADD KEY `fk_user.locale_id` (`locale_id`, `locale_tenant_id`),
            ADD CONSTRAINT `fk_user.locale_id` FOREIGN KEY (`locale_id`, `locale_tenant_id`) REFERENCES `locale` (`id`, `tenant_id`)  ON DELETE RESTRICT ON UPDATE CASCADE'
        );

        $connection->executeQuery('
            ALTER TABLE `language`
            
            MODIFY `locale_id` binary(16) NULL,
            MODIFY `locale_version_id` binary(16) NULL,
            MODIFY `locale_tenant_id` binary(16) NULL,

            ADD KEY `fk_language.locale_id` (`locale_id`, `locale_tenant_id`),
            ADD CONSTRAINT `fk_language.locale_id` FOREIGN KEY (`locale_id`, `locale_tenant_id`) REFERENCES `locale` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
            ADD CONSTRAINT `uniqueLocale` UNIQUE (locale_id, locale_tenant_id)'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `locale`
            DROP COLUMN `version_id`'
        );

        $connection->executeQuery('
            ALTER TABLE `locale_translation`
            DROP COLUMN `locale_version_id`
        ');

        $connection->executeQuery('
            ALTER TABLE `user`
            DROP COLUMN `locale_version_id`'
        );

        $connection->executeQuery('
            ALTER TABLE `language`
            DROP COLUMN `locale_version_id`'
        );
    }
}
