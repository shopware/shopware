<?php

class Migrations_Migration622 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<EOD
ALTER TABLE `s_emotion` ADD `seo_title` varchar(255) NOT NULL AFTER `landingpage_teaser`;
EOD;
        $this->addSql($sql);
    }
}
