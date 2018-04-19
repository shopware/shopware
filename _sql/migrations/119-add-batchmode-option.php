<?php
class Migrations_Migration119 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM s_core_config_forms WHERE name='Frontend30');

INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
(NULL, @parent, 'moveBatchModeEnabled', 'b:0;', 'Kategorien im Batch-Modus verschieben', NULL, 'checkbox', 0, 0, 0, NULL, NULL, 'a:0:{}');
EOD;

        $this->addSql($sql);
    }
}
