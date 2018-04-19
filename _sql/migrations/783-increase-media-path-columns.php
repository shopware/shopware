<?php

class Migrations_Migration783 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_emarketing_banners` CHANGE `img` `img` varchar(255) NOT NULL;");
        $this->addSql("ALTER TABLE `s_articles_supplier` CHANGE `img` `img` varchar(255) NOT NULL;");
    }
}
