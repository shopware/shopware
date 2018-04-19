<?php

class Migrations_Migration809 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql(<<<SQL
DROP TABLE `s_core_sessions`;
SQL
        );

        $this->addSql(<<<SQL
CREATE TABLE `s_core_sessions` (
    `id` VARCHAR(128) NOT NULL PRIMARY KEY,
    `data` MEDIUMBLOB NOT NULL,
    `modified` INTEGER UNSIGNED NOT NULL,
    `expiry` MEDIUMINT NOT NULL
) COLLATE utf8_bin, ENGINE = InnoDB;
SQL
        );

        $this->addSql(<<<SQL
DROP TABLE `s_core_sessions_backend`;
SQL
        );

        $this->addSql(<<<SQL
CREATE TABLE `s_core_sessions_backend` (
    `id` VARCHAR(128) NOT NULL PRIMARY KEY,
    `data` MEDIUMBLOB NOT NULL,
    `modified` INTEGER UNSIGNED NOT NULL,
    `expiry` MEDIUMINT NOT NULL
) COLLATE utf8_bin, ENGINE = InnoDB;
SQL
        );
    }
}
