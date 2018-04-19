<?php
class Migrations_Migration480 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @parentFormId = (SELECT `id` FROM `s_core_config_forms` WHERE `name` = 'SwagMultiEdit');

        UPDATE `s_core_config_forms` SET `label` =  'Mehrfachänderung' WHERE `id` = @parentFormId;
        INSERT IGNORE INTO `s_core_config_form_translations` (`form_id`, `locale_id`, `label`)
                                               VALUES (@parentFormId, 2, 'Multi edit');
EOD;

        $this->addSql($sql);
    }
}
