<?php


class Migrations_Migration727 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parentForm = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'Other' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_config_forms` (`parent_id`, `name`, `label`, `description`, `position`, `scope`, `plugin_id`) VALUES
(@parentForm , 'CoreLicense', 'Shopware-Lizenz', NULL, 0, 0, NULL);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
SET @form = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'CoreLicense' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
SET @localeEnGb = (SELECT id FROM `s_core_locales` WHERE `locale` = 'en_GB' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_config_form_translations` (`form_id`, `locale_id`, `label`, `description`) VALUES
(@form , @localeEnGb, 'Shopware license', NULL);
EOD;
        $this->addSql($sql);
    }
}
