<?php

class Migrations_Migration633 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
ALTER TABLE `s_article_img_mapping_rules`
ADD INDEX `mapping_id` (`mapping_id`),
ADD INDEX `option_id` (`option_id`);
SQL;

        $this->addSql($sql);
    }
}
