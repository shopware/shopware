<?php

class Migrations_Migration812 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @formId = (SELECT id FROM `s_core_config_forms` WHERE name='Frontend30');
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `options`)
VALUES (NULL, @formId, 'maxStoreFrontLimit', 'i:100;', 'Maximale Anzahl Produkte pro Seite', NULL, 'number', '0', '0', '0', NULL);
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
SET @elementId = (SELECT id FROM s_core_config_elements WHERE name= 'maxStoreFrontLimit');
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT INTO `s_core_config_element_translations` (`id`, `element_id`, `locale_id`, `label`, `description`)
VALUES (NULL, @elementId, '2', 'Maximum number of items per page', NULL);
EOD;

        $this->addSql($sql);
    }
}
