<?php
class Migrations_Migration394 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend33' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
            (@parent, 'showCompanySelectField', 'b:1;', '"Ich bin" Auswahlfeld anzeigen', 'Wenn das Auswahlfeld nicht angezeigt wird, wird die Registrierung immer als Privatkunde durchgeführt.', 'checkbox', 1, 0, 1, NULL, NULL, 'a:0:{}');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            SET @newElementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'showCompanySelectField' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
            VALUES (@newElementId, '2', 'Show "I am" select field', 'If this option is false, all registrations will be done as a private customer.');
EOD;
        $this->addSql($sql);
    }
}
