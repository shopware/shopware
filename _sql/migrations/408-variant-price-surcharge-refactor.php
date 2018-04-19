<?php
class Migrations_Migration408 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->createNewTable();
        $this->prepareOldData();
        $this->migrateData();
        $this->cleanUp();
    }

    /**
     * @return string
     */
    protected function createNewTable()
    {
        $this->addSql('DROP TABLE IF EXISTS s_article_configurator_price_variations');

        $sql = <<<EOT
            CREATE TABLE `s_article_configurator_price_variations` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `configurator_set_id` int(10) unsigned NOT NULL,
              `variation` decimal(10,3) NOT NULL,
              `options` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
              `is_gross` int(1) DEFAULT 0,
              PRIMARY KEY (`id`),
              KEY `configurator_set_id` (`configurator_set_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOT;
        $this->addSql($sql);
    }

    /**
     * "Hack" the data so that parent_id < child_id.
     * This will help ensure that the resulting merged column is properly sorted.
     */
    protected function prepareOldData()
    {
        $sql = <<<EOT
            UPDATE s_article_configurator_price_surcharges
            SET child_id = (@temp:=child_id), child_id = parent_id, parent_id = @temp
            WHERE child_id IS NOT NULL AND child_id < parent_id
EOT;
        $this->addSql($sql);
    }

    protected function migrateData()
    {
        $sql = <<<EOT
            INSERT IGNORE INTO s_article_configurator_price_variations
              (id, configurator_set_id, variation, options)
            SELECT
              id,
              configurator_set_id,
              surcharge,
              IF(COALESCE(parent_id, child_id) IS NULL, NULL, CONCAT('|', CONCAT_WS('|', parent_id, child_id),'|')) as options
            FROM
              s_article_configurator_price_surcharges;
EOT;
        $this->addSql($sql);
    }

    protected function cleanUp()
    {
        // Drop old table
        $this->addSql('DROP TABLE IF EXISTS s_article_configurator_price_surcharges');
    }
}
