<?php

class Migrations_Migration466 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql(
            "UPDATE `s_core_rewrite_urls` SET
            `org_path` = REPLACE(
              `org_path`,
              'sViewport=supplier&sSupplier=',
              'sViewport=listing&sAction=manufacturer&sSupplier='
            )
            WHERE `org_path` LIKE 'sViewport=supplier&sSupplier=%';"
        );
    }
}
