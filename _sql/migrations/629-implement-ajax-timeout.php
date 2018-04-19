<?php

class Migrations_Migration629 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOF'
    SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Auth' LIMIT 1);
    SET @elementId = (SELECT id FROM s_core_config_elements WHERE form_id = @formId AND name = 'backendTimeout' LIMIT 1);

    UPDATE s_core_config_element_translations SET label = 'PHP timeout' WHERE element_id = @elementId;
    UPDATE s_core_config_elements SET label = 'PHP Timeout' WHERE id = @elementId;
EOF;

        $this->addSql($sql);

        $sql = <<<'EOF'
        SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Auth' LIMIT 1);

        INSERT INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
        VALUES (NULL, @formId, 'ajaxTimeout', 'i:30;', 'Ajax Timeout', 'Definiert die maximale Ausführungszeit für ExtJS Ajax Requests (in Sekunden)', 'number', '1', '0', '0', NULL, NULL, 'a:1:{s:8:"minValue";i:6;}');
EOF;
        $this->addSql($sql);

        $sql = <<<'EOF'
        SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'ajaxTimeout' LIMIT 1);

        INSERT INTO `s_core_config_element_translations` (`id`, `element_id`, `locale_id`, `label`, `description`)
        VALUES (NULL, @elementId, '2', 'Ajax timeout', 'Defines the max execution time for ExtJS ajax requests (in seconds)');
EOF;
        $this->addSql($sql);
    }
}
