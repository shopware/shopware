<?php
class Migrations_Migration227 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
    SET @elementId = (SELECT id FROM s_core_config_elements WHERE name ='forceCanonicalHttp' LIMIT 1);

    INSERT IGNORE INTO `s_core_config_element_translations` (`id` ,`element_id` ,`locale_id` ,`label` ,`description`)
    VALUES (NULL,  @elementId,  '2',  'Force http canonical url', NULL);
EOD;

        $this->addSql($sql);
    }
}
