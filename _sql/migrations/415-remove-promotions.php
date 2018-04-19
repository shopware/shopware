<?php
class Migrations_Migration415 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("DROP TABLE s_emarketing_promotions");
        $this->addSql("DROP TABLE s_emarketing_promotion_articles");
        $this->addSql("DROP TABLE s_emarketing_promotion_banner");
        $this->addSql("DROP TABLE s_emarketing_promotion_containers");
        $this->addSql("DROP TABLE s_emarketing_promotion_html");
        $this->addSql("DROP TABLE s_emarketing_promotion_links");
        $this->addSql("DROP TABLE s_emarketing_promotion_main");
        $this->addSql("DROP TABLE s_emarketing_promotion_positions");
    }
}
