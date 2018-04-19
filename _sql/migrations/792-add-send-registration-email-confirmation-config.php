<?php

class Migrations_Migration792 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->deletePluginFormEntry();

        $sql = <<<'EOD'
SET @formId = (SELECT id FROM `s_core_config_forms` WHERE name='Frontend33');
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `options`)
VALUES (NULL, @formId, 'sendRegisterConfirmation', 'b:1;', 'Bestätigungsmail nach Registrierung verschicken', NULL, 'boolean', '0', '0', '0', NULL);
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
SET @elementId = (SELECT id FROM s_core_config_elements WHERE name='sendRegisterConfirmation');
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT INTO `s_core_config_element_translations` (`id`, `element_id`, `locale_id`, `label`, `description`)
VALUES (NULL, @elementId, '2', 'Send confirmation email after registration', NULL);
EOD;

        $this->addSql($sql);
    }

    private function deletePluginFormEntry()
    {
        $sql = <<<'EOD'
DELETE FROM `s_core_config_elements` WHERE name='disableRegisterSendConfirmation'
EOD;

        $this->addSql($sql);
    }
}