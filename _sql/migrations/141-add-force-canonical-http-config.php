<?php
class Migrations_Migration141 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
    SET @formId = (SELECT id FROM s_core_config_forms WHERE label = 'SEO/Router-Einstellungen' LIMIT 1);

    INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
    (NULL, @formId, 'forceCanonicalHttp', 'b:1;', 'Canonical immer mit HTTP', NULL, 'boolean', 0, 0, 1, NULL, NULL, NULL);

    SET @elementId = (SELECT id FROM s_core_config_elements WHERE name ='forceUnsecureCanonical' LIMIT 1);

    INSERT IGNORE INTO `s_core_config_element_translations` (`id` ,`element_id` ,`locale_id` ,`label` ,`description`)
    VALUES (NULL,  @elementId,  '2',  'Force http canonical url', NULL);
EOD;

        $this->addSql($sql);
    }
}



