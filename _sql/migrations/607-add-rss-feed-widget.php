<?php

class Migrations_Migration607 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("INSERT INTO `s_core_widgets` (`name`, `label`) VALUES ('swag-shopware-news-widget', 'shopware News');");
    }
}
