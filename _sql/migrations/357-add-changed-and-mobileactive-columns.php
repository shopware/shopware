<?php

class Migrations_Migration357 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $dateTime = new DateTime();
        $date = $dateTime->format('Y-m-d H:i:s');

        $this->addSql(<<<EOD
            ALTER TABLE `s_articles_supplier` ADD `changed` DATETIME NOT NULL DEFAULT "$date";
EOD
        );

        $this->addSql(<<<EOD
ALTER TABLE `s_cms_static` ADD `changed` DATETIME NOT NULL DEFAULT "$date";
EOD
        );

        $this->addSql(<<<EOD
ALTER TABLE `s_core_paymentmeans` ADD `mobile_inactive` INT( 1 ) NOT NULL DEFAULT 0;
EOD
        );
    }
}
